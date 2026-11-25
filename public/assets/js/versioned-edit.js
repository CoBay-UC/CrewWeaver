import {api, ApiError} from './api.js';

export function changedFields(fields, baseline) {
    return Object.fromEntries(Object.entries(fields).filter(([key, value]) => String(baseline[key] ?? '') !== String(value ?? '')));
}

export async function saveVersioned(path, baseline, fields, reconcile) {
    const draft = changedFields(fields, baseline);
    if (!Object.keys(draft).length) return;
    let version = baseline.version;
    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            return await api(path, {method: 'PATCH', body: {...draft, version: Number(version)}});
        } catch (error) {
            if (error.status !== 409) throw error;
            const latest = await api(path, {cache: 'no-store'});
            if (Number(latest.version) === Number(version)) throw error;
            const overlap = Object.keys(changedFields(draft, latest))
                .filter(key => String(latest[key] ?? '') !== String(baseline[key] ?? ''));
            const locked = Number(latest.is_archived) === 1 || latest.permissions?.edit === false;
            if (overlap.length || attempt === 2 || locked) {
                reconcile(latest, draft);
                if (locked) throw error;
                const message = overlap.length
                    ? 'Another edit changed ' + overlap.join(', ') + '. Your edits are still here. Review them and save again to apply them.'
                    : 'The record changed again. Review your edits and save again.';
                throw new ApiError(message, 409);
            }
            version = latest.version;
        }
    }
}
