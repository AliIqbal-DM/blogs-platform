import { brandButtonClass } from '@/brand';
import InputError from '@/Components/InputError';
import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const COVER_TYPES = 'image/jpeg,image/png,image/webp';

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

/** Object URL for a picked file, revoked when the file changes or the form unmounts. */
function useFilePreview(file) {
    const [url, setUrl] = useState(null);

    useEffect(() => {
        if (!file) {
            setUrl(null);

            return undefined;
        }

        const objectUrl = URL.createObjectURL(file);
        setUrl(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);

    return url;
}

function CoverField({ form, currentCoverUrl }) {
    const { data, setData, errors, progress } = form;
    const pickedUrl = useFilePreview(data.cover);
    const previewUrl = pickedUrl ?? (data.remove_cover ? null : currentCoverUrl);

    return (
        <div>
            <label htmlFor="cover" className="block text-sm font-medium text-zinc-700">
                Cover image <span className="font-normal text-zinc-600">(optional)</span>
            </label>

            <div className="mt-2 flex flex-col gap-4 sm:flex-row sm:items-start">
                <div className="aspect-[16/9] w-full shrink-0 overflow-hidden rounded-md border border-zinc-200 bg-zinc-100 sm:w-48">
                    {previewUrl ? (
                        <img
                            src={previewUrl}
                            alt={pickedUrl ? 'Preview of the selected cover image' : 'Current cover image'}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <div className="flex h-full w-full items-center justify-center text-zinc-400">
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={1.5}
                                className="h-8 w-8"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0 0 21.75 19.5V4.5A1.5 1.5 0 0 0 20.25 3H3.75A1.5 1.5 0 0 0 2.25 4.5v15A1.5 1.5 0 0 0 3.75 21Zm10.5-11.25h.008v.008h-.008V9.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"
                                />
                            </svg>
                            <span className="sr-only">No cover image</span>
                        </div>
                    )}
                </div>

                <div className="min-w-0 flex-1 space-y-3">
                    <input
                        id="cover"
                        type="file"
                        accept={COVER_TYPES}
                        aria-describedby="cover-hint"
                        className="block w-full cursor-pointer text-sm text-zinc-700 file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-800 file:transition-colors file:duration-200 hover:file:bg-zinc-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]"
                        onChange={(e) => setData('cover', e.target.files[0] ?? null)}
                    />
                    <p id="cover-hint" className="text-xs text-zinc-600">
                        JPG, PNG or WebP, up to 4 MB. A wide image (16:9) works best.
                    </p>

                    {currentCoverUrl && !data.cover && (
                        <label className="flex cursor-pointer items-center gap-2 text-sm text-zinc-700">
                            <input
                                type="checkbox"
                                checked={data.remove_cover}
                                className="rounded border-zinc-300 text-[var(--brand)] focus:ring-[var(--brand)]"
                                onChange={(e) => setData('remove_cover', e.target.checked)}
                            />
                            Remove cover
                        </label>
                    )}

                    {progress && (
                        <progress
                            value={progress.percentage}
                            max="100"
                            aria-label="Upload progress"
                            className="h-1.5 w-full overflow-hidden rounded-full accent-[var(--brand)]"
                        />
                    )}
                </div>
            </div>
            <InputError message={errors.cover} className="mt-2" />
        </div>
    );
}

export default function Form({ form, onSubmit, submitLabel, currentCoverUrl = null }) {
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

            <CoverField form={form} currentCoverUrl={currentCoverUrl} />

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
                    className={brandButtonClass}
                >
                    {processing ? 'Saving…' : submitLabel}
                </button>
            </div>
        </form>
    );
}
