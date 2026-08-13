// A small, hand-written IndexedDB wrapper. No storage/offline package — per
// Docs/adr/0002-offline-sync-strategy.md §10 that machinery is built by hand
// on purpose.
//
// Object stores:
//   catalog   — pulled rows (product/customer/route/visit_schedule), keyed
//               by "<entityType>:<id>".
//   pricebook — one row per (customer_id, product_id), replaced wholesale on
//               every successful pricebook pull.
//   queue     — entities captured offline, waiting to push. Keyed by their
//               own client_id, so re-queuing the same one is impossible.
//   meta      — small singleton facts: cursors, last_synced_at, device_id.
//
// Every read-then-write here chains IDBRequests inside one transaction
// rather than awaiting across them — awaiting something outside the
// transaction (a fetch, a timer) lets IndexedDB auto-commit it under you,
// which is the standard footgun this file exists to avoid.

// Namespaced per rep, not just per origin. A shared device (a company
// tablet handed between reps at shift change) must not let Rep B's login
// drain Rep A's still-queued, not-yet-synced captures into Rep B's session —
// found by a strict review of this exact scenario. Each rep gets an
// isolated local database; Rep A's queue is simply invisible until Rep A
// logs back in, not lost and not reattributed.
const DB_NAME = `dukaflow-rep-${window.DUKAFLOW_REP?.id ?? 'anonymous'}`;
const DB_VERSION = 1;

let dbPromise = null;

function openDb() {
    if (dbPromise) {
        return dbPromise;
    }

    dbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            const database = request.result;

            if (!database.objectStoreNames.contains('catalog')) {
                database.createObjectStore('catalog', { keyPath: 'key' });
            }
            if (!database.objectStoreNames.contains('pricebook')) {
                const store = database.createObjectStore('pricebook', { keyPath: 'key' });
                store.createIndex('customer_id', 'customer_id');
            }
            if (!database.objectStoreNames.contains('queue')) {
                database.createObjectStore('queue', { keyPath: 'client_id' });
            }
            if (!database.objectStoreNames.contains('meta')) {
                database.createObjectStore('meta', { keyPath: 'key' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    return dbPromise;
}

function tx(storeNames, mode) {
    return openDb().then((database) => database.transaction(storeNames, mode));
}

function settle(transaction, result) {
    return new Promise((resolve, reject) => {
        transaction.oncomplete = () => resolve(result);
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });
}

export const db = {
    async putCatalogRows(entityType, rows) {
        const transaction = await tx('catalog', 'readwrite');
        const store = transaction.objectStore('catalog');

        for (const row of rows) {
            store.put({ key: `${entityType}:${row.id}`, entityType, id: row.id, updatedAt: row.updated_at, data: row.data });
        }

        return settle(transaction);
    },

    async allOfType(entityType) {
        const transaction = await tx('catalog', 'readonly');
        const request = transaction.objectStore('catalog').getAll();

        return settle(transaction, null).then(
            () => request.result.filter((row) => row.entityType === entityType),
        );
    },

    // Drops anything cached for this entity type that isn't in the
    // server's authoritative id set — a route reassigned away from this
    // rep, or a hard delete, both just stop being in it. See
    // Docs/adr/0007-reconciling-stale-device-caches.md. Only called with
    // the last page of a pull, once validIds is actually complete.
    async pruneCatalogRows(entityType, validIds) {
        const transaction = await tx('catalog', 'readwrite');
        const store = transaction.objectStore('catalog');
        const valid = new Set(validIds);
        const request = store.getAll();

        request.onsuccess = () => {
            for (const row of request.result) {
                if (row.entityType === entityType && !valid.has(row.id)) {
                    store.delete(row.key);
                }
            }
        };

        return settle(transaction);
    },

    async replacePricebook(prices) {
        const transaction = await tx('pricebook', 'readwrite');
        const store = transaction.objectStore('pricebook');

        store.clear();
        for (const price of prices) {
            store.put({ key: `${price.customer_id}:${price.product_id}`, ...price });
        }

        return settle(transaction);
    },

    async pricesForCustomer(customerId) {
        const transaction = await tx('pricebook', 'readonly');
        const request = transaction.objectStore('pricebook').index('customer_id').getAll(IDBKeyRange.only(customerId));

        return settle(transaction, null).then(() => request.result);
    },

    async enqueue(entity) {
        const transaction = await tx('queue', 'readwrite');
        transaction.objectStore('queue').put({
            ...entity,
            queued_at: new Date().toISOString(),
            status: 'pending',
            message: null,
            attempts: 0,
            next_attempt_at: null,
        });

        return settle(transaction);
    },

    async allQueued() {
        const transaction = await tx('queue', 'readonly');
        const request = transaction.objectStore('queue').getAll();

        return settle(transaction, null).then(() => request.result);
    },

    /** @param {{attempts?: number, nextAttemptAt?: string|null}} retry */
    async markQueued(clientId, status, message = null, retry = {}) {
        const transaction = await tx('queue', 'readwrite');
        const store = transaction.objectStore('queue');
        const getRequest = store.get(clientId);

        getRequest.onsuccess = () => {
            if (getRequest.result) {
                store.put({
                    ...getRequest.result,
                    status,
                    message,
                    attempts: retry.attempts ?? getRequest.result.attempts ?? 0,
                    next_attempt_at: retry.nextAttemptAt ?? null,
                });
            }
        };

        return settle(transaction);
    },

    async removeQueued(clientId) {
        const transaction = await tx('queue', 'readwrite');
        transaction.objectStore('queue').delete(clientId);

        return settle(transaction);
    },

    async getMeta(key) {
        const transaction = await tx('meta', 'readonly');
        const request = transaction.objectStore('meta').get(key);

        return settle(transaction, null).then(() => request.result?.value ?? null);
    },

    async setMeta(key, value) {
        const transaction = await tx('meta', 'readwrite');
        transaction.objectStore('meta').put({ key, value });

        return settle(transaction);
    },
};
