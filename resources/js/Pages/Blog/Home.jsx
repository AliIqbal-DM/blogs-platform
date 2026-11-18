import BlogLayout from '@/Layouts/BlogLayout';
import { Head, Link, usePage } from '@inertiajs/react';

const formatDate = (iso) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(new Date(iso));

const pagerClass =
    'cursor-pointer rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-800 transition-colors duration-200 hover:bg-zinc-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 focus-visible:ring-offset-2';

export default function Home({ posts }) {
    const { tenant } = usePage().props;

    return (
        <BlogLayout>
            <Head title={tenant.name} />

            <h1 className="sr-only">{tenant.name}</h1>

            {posts.data.length === 0 ? (
                <p className="py-16 text-center text-zinc-600">No posts published yet.</p>
            ) : (
                <ol className="divide-y divide-zinc-200">
                    {posts.data.map((post, index) => (
                        <li key={post.id} className="py-8 first:pt-0">
                            <article>
                                {post.cover_url && (
                                    <img
                                        src={post.cover_url}
                                        alt={`Cover image for “${post.title}”`}
                                        loading={index === 0 ? 'eager' : 'lazy'}
                                        decoding="async"
                                        className="mb-5 aspect-[16/9] w-full rounded-lg border border-zinc-200 bg-zinc-100 object-cover"
                                    />
                                )}
                                <p className="text-sm text-zinc-600">
                                    <time dateTime={post.published_at}>{formatDate(post.published_at)}</time>
                                    {post.author && <> · {post.author}</>}
                                </p>
                                <h2 className="mt-1 text-2xl font-semibold tracking-tight">
                                    <Link
                                        href={route('tenant.posts.show', post.slug)}
                                        className="cursor-pointer rounded-sm hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900"
                                    >
                                        {post.title}
                                    </Link>
                                </h2>
                                <p className="mt-2 line-clamp-3 max-w-prose whitespace-pre-line leading-relaxed text-zinc-700">
                                    {post.excerpt}
                                </p>
                            </article>
                        </li>
                    ))}
                </ol>
            )}

            {posts.last_page > 1 && (
                <nav aria-label="Pagination" className="mt-10 flex items-center justify-between border-t border-zinc-200 pt-6">
                    {posts.prev_page_url ? (
                        <Link href={posts.prev_page_url} className={pagerClass}>
                            Newer posts
                        </Link>
                    ) : (
                        <span />
                    )}
                    <span className="text-sm text-zinc-600">
                        Page {posts.current_page} of {posts.last_page}
                    </span>
                    {posts.next_page_url ? (
                        <Link href={posts.next_page_url} className={pagerClass}>
                            Older posts
                        </Link>
                    ) : (
                        <span />
                    )}
                </nav>
            )}
        </BlogLayout>
    );
}
