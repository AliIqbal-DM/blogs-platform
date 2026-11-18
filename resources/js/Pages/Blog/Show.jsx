import { brandLinkClass } from '@/brand';
import BlogLayout from '@/Layouts/BlogLayout';
import { Head, Link, usePage } from '@inertiajs/react';

const formatDate = (iso) =>
    new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(new Date(iso));

export default function Show({ post }) {
    const { tenant } = usePage().props;

    return (
        <BlogLayout>
            <Head title={`${post.title} · ${tenant.name}`} />

            <article className="mx-auto max-w-prose">
                <header>
                    <span aria-hidden="true" className="mb-4 block h-1 w-12 rounded-full bg-[var(--brand)]" />
                    <p className="text-sm text-zinc-600">
                        <time dateTime={post.published_at}>{formatDate(post.published_at)}</time>
                        {post.author && <> · {post.author}</>}
                    </p>
                    <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">{post.title}</h1>
                </header>

                {post.cover_url && (
                    <img
                        src={post.cover_url}
                        alt={`Cover image for “${post.title}”`}
                        fetchpriority="high"
                        className="mt-8 aspect-[16/9] w-full rounded-lg border border-zinc-200 bg-zinc-100 object-cover"
                    />
                )}

                {/* Plain text only: rendered as a text node, never as HTML. */}
                <div className="mt-8 whitespace-pre-line break-words text-lg leading-8 text-zinc-800">
                    {post.body}
                </div>

                <footer className="mt-12 border-t border-zinc-200 pt-6">
                    <Link
                        href={route('tenant.home')}
                        className={`text-sm font-medium text-zinc-700 hover:text-zinc-900 ${brandLinkClass}`}
                    >
                        ← All posts
                    </Link>
                </footer>
            </article>
        </BlogLayout>
    );
}
