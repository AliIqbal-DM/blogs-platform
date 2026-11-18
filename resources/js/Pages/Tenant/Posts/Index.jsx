import Modal from '@/Components/Modal';
import WriterLayout from '@/Layouts/WriterLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

const STATUS_STYLES = {
    draft: 'bg-zinc-100 text-zinc-700 ring-zinc-300',
    scheduled: 'bg-amber-50 text-amber-800 ring-amber-300',
    published: 'bg-green-50 text-green-800 ring-green-300',
};

const STATUS_LABELS = {
    draft: 'Draft',
    scheduled: 'Scheduled',
    published: 'Published',
};

const formatDateTime = (iso) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));

function StatusBadge({ status }) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${STATUS_STYLES[status]}`}
        >
            {STATUS_LABELS[status]}
        </span>
    );
}

function StatusDetail({ post }) {
    if (post.status === 'scheduled' && post.publish_at) {
        return <>Publishes {formatDateTime(post.publish_at)}</>;
    }

    if (post.status === 'published' && post.published_at) {
        return <>Published {formatDateTime(post.published_at)}</>;
    }

    return <>Updated {formatDateTime(post.updated_at)}</>;
}

function CoverThumbnail({ post }) {
    if (!post.cover_url) {
        return (
            <span
                aria-hidden="true"
                className="hidden aspect-[16/9] w-20 shrink-0 rounded border border-dashed border-zinc-300 bg-zinc-50 sm:block"
            />
        );
    }

    return (
        <img
            src={post.cover_url}
            alt={`Cover image for “${post.title}”`}
            loading="lazy"
            className="aspect-[16/9] w-20 shrink-0 rounded border border-zinc-200 object-cover"
        />
    );
}

function NewPostButton() {
    return (
        <Link
            href={route('tenant.dashboard.posts.create')}
            className="inline-flex cursor-pointer items-center rounded-md bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition-opacity duration-200 hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2"
        >
            New post
        </Link>
    );
}

function DeletePostModal({ post, onClose }) {
    const { delete: destroy, processing } = useForm();

    const confirm = () => {
        destroy(route('tenant.dashboard.posts.destroy', post.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Modal show={post !== null} onClose={onClose} maxWidth="md">
            {post && (
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-zinc-900">Delete this post?</h2>
                    <p className="mt-2 text-sm text-zinc-600">
                        “{post.title}” will be permanently deleted. This cannot be undone.
                    </p>
                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={onClose}
                            className="cursor-pointer rounded-md px-4 py-2 text-sm font-medium text-zinc-700 transition-colors duration-200 hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-400"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={confirm}
                            disabled={processing}
                            className="cursor-pointer rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white transition-colors duration-200 hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing ? 'Deleting…' : 'Delete post'}
                        </button>
                    </div>
                </div>
            )}
        </Modal>
    );
}

export default function Index({ posts }) {
    const [postToDelete, setPostToDelete] = useState(null);

    return (
        <WriterLayout>
            <Head title="Posts" />

            <div className="mb-6 flex items-center justify-between gap-4">
                <h1 className="text-2xl font-semibold tracking-tight">Posts</h1>
                {posts.length > 0 && <NewPostButton />}
            </div>

            {posts.length === 0 ? (
                <div className="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center">
                    <p className="text-sm font-medium text-zinc-900">No posts yet</p>
                    <p className="mt-1 text-sm text-zinc-600">Write the first post for this blog.</p>
                    <div className="mt-4">
                        <NewPostButton />
                    </div>
                </div>
            ) : (
                <ul className="divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 bg-white">
                    {posts.map((post) => (
                        <li key={post.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex min-w-0 items-center gap-4">
                                <CoverThumbnail post={post} />
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2">
                                        <StatusBadge status={post.status} />
                                        <Link
                                            href={route('tenant.dashboard.posts.edit', post.id)}
                                            className="cursor-pointer truncate font-medium text-zinc-900 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]"
                                        >
                                            {post.title}
                                        </Link>
                                    </div>
                                    <p className="mt-1 text-xs text-zinc-600">
                                        <StatusDetail post={post} />
                                        {post.author && <> · by {post.author}</>}
                                    </p>
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-2">
                                {post.status === 'published' && (
                                    <a
                                        href={route('tenant.posts.show', post.slug)}
                                        className="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium text-zinc-700 transition-colors duration-200 hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]"
                                    >
                                        View
                                    </a>
                                )}
                                <Link
                                    href={route('tenant.dashboard.posts.edit', post.id)}
                                    className="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium text-zinc-700 transition-colors duration-200 hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]"
                                >
                                    Edit
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => setPostToDelete(post)}
                                    className="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium text-red-700 transition-colors duration-200 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                                >
                                    Delete
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <DeletePostModal post={postToDelete} onClose={() => setPostToDelete(null)} />
        </WriterLayout>
    );
}
