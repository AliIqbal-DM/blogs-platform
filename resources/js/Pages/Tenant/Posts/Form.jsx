import InputError from '@/Components/InputError';
import { Link } from '@inertiajs/react';

const STATUSES = [
    { value: 'draft', label: 'Draft', hint: 'Only writers can see it.' },
    { value: 'scheduled', label: 'Scheduled', hint: 'Publishes at the time you choose.' },
    { value: 'published', label: 'Published', hint: 'Live on the blog now.' },
];

const fieldClass =
    'mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]';

const pad = (n) => String(n).padStart(2, '0');

/** ISO string from the server (UTC) to a value for <input type="datetime-local"> in the writer's time zone. */
export function toLocalInput(iso) {
    if (!iso) {
        return '';
    }

    const d = new Date(iso);

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/** Sends publish_at as an ISO string in UTC, and only for scheduled posts. */
export function toPayload(data) {
    return {
        ...data,
        publish_at:
            data.status === 'scheduled' && data.publish_at
                ? new Date(data.publish_at).toISOString()
                : null,
    };
}

export default function Form({ form, onSubmit, submitLabel }) {
    const { data, setData, errors, processing } = form;

    return (
        <form
            onSubmit={onSubmit}
            className="space-y-6 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm"
        >
            <div>
                <label htmlFor="title" className="block text-sm font-medium text-zinc-700">
                    Title
                </label>
                <input
                    id="title"
                    type="text"
                    value={data.title}
                    maxLength={255}
                    required
                    autoFocus
                    className={fieldClass}
                    onChange={(e) => setData('title', e.target.value)}
                />
                <InputError message={errors.title} className="mt-2" />
            </div>

            <div>
                <label htmlFor="body" className="block text-sm font-medium text-zinc-700">
                    Body
                </label>
                <textarea
                    id="body"
                    rows={14}
                    value={data.body}
                    required
                    aria-describedby="body-hint"
                    className={`${fieldClass} leading-relaxed`}
                    onChange={(e) => setData('body', e.target.value)}
                />
                <p id="body-hint" className="mt-1 text-xs text-zinc-600">
                    Plain text. Leave a blank line between paragraphs.
                </p>
                <InputError message={errors.body} className="mt-2" />
            </div>

            <fieldset>
                <legend className="text-sm font-medium text-zinc-700">Status</legend>
                <div className="mt-2 grid gap-3 sm:grid-cols-3">
                    {STATUSES.map((status) => (
                        <label
                            key={status.value}
                            className="flex cursor-pointer gap-3 rounded-md border border-zinc-200 p-3 transition-colors duration-200 hover:bg-zinc-50 has-[:checked]:border-[var(--brand)] has-[:checked]:bg-zinc-50"
                        >
                            <input
                                type="radio"
                                name="status"
                                value={status.value}
                                checked={data.status === status.value}
                                className="mt-0.5 border-zinc-300 text-[var(--brand)] focus:ring-[var(--brand)]"
                                onChange={(e) => setData('status', e.target.value)}
                            />
                            <span>
                                <span className="block text-sm font-medium text-zinc-900">
                                    {status.label}
                                </span>
                                <span className="block text-xs text-zinc-600">{status.hint}</span>
                            </span>
                        </label>
                    ))}
                </div>
                <InputError message={errors.status} className="mt-2" />
            </fieldset>

            {data.status === 'scheduled' && (
                <div>
                    <label htmlFor="publish_at" className="block text-sm font-medium text-zinc-700">
                        Publish at
                    </label>
                    <input
                        id="publish_at"
                        type="datetime-local"
                        value={data.publish_at}
                        required
                        aria-describedby="publish_at-hint"
                        className={`${fieldClass} sm:max-w-xs`}
                        onChange={(e) => setData('publish_at', e.target.value)}
                    />
                    <p id="publish_at-hint" className="mt-1 text-xs text-zinc-600">
                        In your time zone ({Intl.DateTimeFormat().resolvedOptions().timeZone}).
                    </p>
                    <InputError message={errors.publish_at} className="mt-2" />
                </div>
            )}

            <div className="flex items-center justify-end gap-3 border-t border-zinc-200 pt-6">
                <Link
                    href={route('tenant.dashboard.posts.index')}
                    className="cursor-pointer rounded-md px-4 py-2 text-sm font-medium text-zinc-700 transition-colors duration-200 hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    disabled={processing}
                    className="cursor-pointer rounded-md bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition-opacity duration-200 hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {processing ? 'Saving…' : submitLabel}
                </button>
            </div>
        </form>
    );
}
