// Pull and push, both hand-built against the endpoints in
// App\Modules\Sync — no sync package, per
// Docs/adr/0002-offline-sync-strategy.md §10.

import { db } from './db';
import { ulid } from './ulid';

const ENTITY_TYPES = ['product', 'customer', 'route', 'visit_schedule'];
const PULL_LIMIT = 500;
const MAX_ATTEMPTS_BEFORE_BACKOFF_CAP = 6;
const PUSH_BATCH_SIZE = 50; // matches PushSyncRequest's entities max:50

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function deviceId() {
    let id = await db.getMeta('device_id');

    if (!id) {
        id = ulid();
        await db.setMeta('device_id', id);
    }

    return id;
}

async function getJson(url) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error(`${url} responded ${response.status}`);
    }

    return response.json();
}

/**
 * Pulls every entity type to exhaustion (has_more keeps going), storing rows
 * as they arrive and remembering each type's cursor for next time.
 */
async function pullAll(onProgress) {
    const device = await deviceId();

    for (const entityType of ENTITY_TYPES) {
        let cursor = await db.getMeta(`cursor:${entityType}`);
        let hasMore = true;

        while (hasMore) {
            const params = new URLSearchParams({ device_id: device, entity_type: entityType, limit: String(PULL_LIMIT) });
            if (cursor) {
                params.set('cursor', cursor);
            }

            const page = await getJson(`/api/sync/pull?${params}`);

            await db.putCatalogRows(entityType, page.rows);
            await db.setMeta(`cursor:${entityType}`, page.next_cursor);

            cursor = page.next_cursor;
            hasMore = page.has_more;
            onProgress?.(entityType, page.rows.length);
        }
    }

    const pricebook = await getJson(`/api/sync/pricebook?device_id=${encodeURIComponent(device)}`);
    await db.replacePricebook(pricebook.prices);
    await db.setMeta('last_synced_at', new Date().toISOString());
}

function chunk(items, size) {
    const chunks = [];
    for (let i = 0; i < items.length; i += size) {
        chunks.push(items.slice(i, i + size));
    }
    return chunks;
}

/**
 * Drains the queue, oldest chunk first. A queued item that comes back "ok"
 * or "conflict" is done — a conflict is a server-side fact now, not
 * something retrying will fix. Only "error" (a transient failure, or a
 * payload the device should stop sending as-is) stays queued, and it stays
 * queued behind a growing backoff rather than being retried on every call.
 */
async function pushQueued(onResult) {
    const now = Date.now();
    const eligible = (await db.allQueued()).filter((item) => {
        if (item.status === 'conflict') {
            return false;
        }
        return !item.next_attempt_at || new Date(item.next_attempt_at).getTime() <= now;
    });

    if (eligible.length === 0) {
        return { pushed: 0, conflicts: 0, errors: 0 };
    }

    const device = await deviceId();
    let conflicts = 0;
    let errors = 0;
    let pushed = 0;

    for (const batch of chunk(eligible, PUSH_BATCH_SIZE)) {
        const entities = batch.map((item) => ({ client_id: item.client_id, entity_type: item.entity_type, data: item.data }));

        const response = await fetch('/api/sync/push', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ device_id: device, device_label: navigator.userAgent.slice(0, 250), entities }),
        });

        if (!response.ok) {
            throw new Error(`push responded ${response.status}`);
        }

        const body = await response.json();
        const byClientId = new Map(batch.map((item) => [item.client_id, item]));

        for (const result of body.results) {
            if (result.status === 'ok') {
                await db.removeQueued(result.client_id);
                pushed += 1;
            } else if (result.status === 'conflict') {
                await db.markQueued(result.client_id, 'conflict', result.message);
                conflicts += 1;
            } else {
                const attempts = (byClientId.get(result.client_id)?.attempts ?? 0) + 1;
                const nextAttemptAt = new Date(now + backoffSeconds(attempts) * 1000).toISOString();
                await db.markQueued(result.client_id, 'error', result.message, { attempts, nextAttemptAt });
                errors += 1;
            }

            onResult?.(result);
        }
    }

    if (pushed > 0) {
        await db.setMeta('last_synced_at', new Date().toISOString());
    }

    return { pushed, conflicts, errors };
}

/** Full round: push what's waiting, then pull what's changed. */
async function syncNow(onProgress) {
    await pushQueued(onProgress);
    await pullAll(onProgress);
}

/**
 * Retries a failed push with backoff that grows per call rather than
 * blocking in a loop — each caller (the online event, the periodic timer,
 * the manual button) just calls this and the delay since the last attempt
 * decides whether it is honoured.
 */
function backoffSeconds(attempt) {
    const capped = Math.min(attempt, MAX_ATTEMPTS_BEFORE_BACKOFF_CAP);
    return Math.min(2 ** capped, 300); // caps at 5 minutes
}

export const sync = { pullAll, pushQueued, syncNow, backoffSeconds, deviceId };
