// The whole route → visit → capture flow, in the browser, no server
// round-trip per interaction. See
// Docs/adr/0002-offline-sync-strategy.md §5.

import { db } from './db';
import { sync } from './sync';
import { ulid } from './ulid';

function isoWeekday(date) {
    const day = date.getDay(); // 0 (Sun) .. 6 (Sat)
    return day === 0 ? 7 : day; // 1 (Mon) .. 7 (Sun), matching DayOfWeek
}

function todayKey() {
    return `visited:${new Date().toISOString().slice(0, 10)}`;
}

// "Visited" can't be read off the queue alone — the whole point of syncing
// is that a completed visit LEAVES the queue, and the checkmark would
// vanish the moment it did. This is its own small, date-scoped record.
async function markVisited(customerId) {
    const key = todayKey();
    const current = (await db.getMeta(key)) ?? [];
    if (!current.includes(customerId)) {
        await db.setMeta(key, [...current, customerId]);
    }
}

function money(minorUnits) {
    return (minorUnits / 100).toFixed(2);
}

export function repApp(repId) {
    return {
        // --- state -----------------------------------------------------
        view: 'route', // 'route' | 'visit' | 'cart'
        online: navigator.onLine,
        syncing: false,
        lastSyncedAt: null,
        pendingCount: 0,
        conflictCount: 0,
        lastMessage: null,

        customers: [], // today's round, in sequence
        visitedToday: new Set(),
        activeCustomer: null,
        activePrices: [], // pricebook rows for the active customer
        cartProducts: [], // activePrices joined with product name/sku/unit
        cart: [], // {product_id, sku, name, unit, quantity, unit_price_minor, price_list_id}
        noSaleReason: '',

        // --- lifecycle ---------------------------------------------------
        async init() {
            window.addEventListener('online', () => { this.online = true; this.trySync(); });
            window.addEventListener('offline', () => { this.online = false; });

            await this.refreshQueueCounts();
            await this.loadToday();

            this.lastSyncedAt = await db.getMeta('last_synced_at');

            if (this.online) {
                await this.trySync();
            }

            setInterval(() => this.trySync(), 60_000);
        },

        async trySync() {
            if (!this.online || this.syncing) {
                return;
            }

            this.syncing = true;
            this.lastMessage = null;

            try {
                await sync.syncNow();
                this.lastSyncedAt = await db.getMeta('last_synced_at');
                await this.loadToday();
                await this.refreshQueueCounts();
            } catch (error) {
                this.lastMessage = 'Could not reach the server. Will try again.';
            } finally {
                this.syncing = false;
            }
        },

        async refreshQueueCounts() {
            const queued = await db.allQueued();
            this.pendingCount = queued.filter((item) => item.status === 'pending' || item.status === 'error').length;
            this.conflictCount = queued.filter((item) => item.status === 'conflict').length;
        },

        // --- today's round -------------------------------------------------
        async loadToday() {
            const [customers, schedules, routes] = await Promise.all([
                db.allOfType('customer'),
                db.allOfType('visit_schedule'),
                db.allOfType('route'),
            ]);

            const myRouteIds = new Set(routes.filter((row) => row.data.sales_rep_id === repId).map((row) => row.id));
            const today = isoWeekday(new Date());

            const dueToday = schedules
                .filter((row) => row.data.day_of_week === today && row.data.is_active)
                .reduce((byCustomer, row) => byCustomer.set(row.data.customer_id, row.data.sequence), new Map());

            const persistedVisited = (await db.getMeta(todayKey())) ?? [];
            const queued = await db.allQueued();
            const queuedVisited = queued
                .filter((item) => item.entity_type === 'order' || item.entity_type === 'visit_outcome')
                .map((item) => item.data.customer_id);
            this.visitedToday = new Set([...persistedVisited, ...queuedVisited]);

            this.customers = customers
                .filter((row) => dueToday.has(row.id) && myRouteIds.has(row.data.route_id) && row.data.is_active)
                .map((row) => ({ id: row.id, ...row.data, sequence: dueToday.get(row.id) }))
                .sort((a, b) => a.sequence - b.sequence);
        },

        // --- visiting a customer -------------------------------------------
        async openCustomer(customer) {
            this.activeCustomer = customer;
            this.activePrices = await db.pricesForCustomer(customer.id);
            this.cartProducts = await this.productsForCart();
            this.cart = [];
            this.noSaleReason = '';
            this.view = 'visit';
        },

        backToRoute() {
            this.activeCustomer = null;
            this.view = 'route';
        },

        async productsForCart() {
            const products = await db.allOfType('product');
            const byId = new Map(products.map((row) => [row.id, row.data]));

            return this.activePrices.map((price) => ({
                ...price,
                name: byId.get(price.product_id)?.name ?? `Product #${price.product_id}`,
                sku: byId.get(price.product_id)?.sku ?? '',
                unit: byId.get(price.product_id)?.unit ?? '',
            }));
        },

        addToCart(priced) {
            const existing = this.cart.find((line) => line.product_id === priced.product_id);

            if (existing) {
                existing.quantity += 1;
                return;
            }

            this.cart.push({
                product_id: priced.product_id,
                name: priced.name,
                sku: priced.sku,
                unit: priced.unit,
                unit_price_minor: priced.unit_price_minor,
                price_list_id: priced.price_list_id,
                quantity: 1,
            });
        },

        changeQuantity(productId, delta) {
            const line = this.cart.find((entry) => entry.product_id === productId);
            if (!line) {
                return;
            }

            line.quantity += delta;
            if (line.quantity <= 0) {
                this.cart = this.cart.filter((entry) => entry.product_id !== productId);
            }
        },

        cartTotal() {
            return this.cart.reduce((sum, line) => sum + line.unit_price_minor * line.quantity, 0);
        },

        formatMoney: money,

        async completeOrder() {
            if (this.cart.length === 0) {
                return;
            }

            const clientId = ulid();
            const now = new Date().toISOString();

            await db.enqueue({
                client_id: clientId,
                entity_type: 'order',
                data: {
                    customer_id: this.activeCustomer.id,
                    route_id: this.activeCustomer.route_id,
                    currency: 'ETB',
                    placed_at: now,
                    lines: this.cart.map((line) => ({
                        product_id: line.product_id,
                        quantity: line.quantity,
                        unit_price_minor: line.unit_price_minor,
                        price_list_id: line.price_list_id,
                    })),
                },
            });

            await markVisited(this.activeCustomer.id);
            this.visitedToday.add(this.activeCustomer.id);
            this.lastMessage = `Order queued for ${this.activeCustomer.name}.`;
            await this.refreshQueueCounts();
            this.backToRoute();
            this.trySync();
        },

        async recordNoSale() {
            if (!this.noSaleReason.trim()) {
                return;
            }

            const clientId = ulid();

            await db.enqueue({
                client_id: clientId,
                entity_type: 'visit_outcome',
                data: {
                    customer_id: this.activeCustomer.id,
                    route_id: this.activeCustomer.route_id,
                    outcome: 'no_sale',
                    reason: this.noSaleReason.trim(),
                    occurred_at: new Date().toISOString(),
                },
            });

            await markVisited(this.activeCustomer.id);
            this.visitedToday.add(this.activeCustomer.id);
            this.lastMessage = `No-sale recorded for ${this.activeCustomer.name}.`;
            await this.refreshQueueCounts();
            this.backToRoute();
            this.trySync();
        },
    };
}
