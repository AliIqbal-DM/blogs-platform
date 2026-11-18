import { brandStyle } from '@/brand';
import { Link, usePage } from '@inertiajs/react';

function BlogMark({ tenant }) {
    if (tenant.logo_url) {
        return (
            <img
                src={tenant.logo_url}
                alt={`${tenant.name} logo`}
                className="h-9 w-9 rounded-md object-cover ring-1 ring-zinc-200"
            />
        );
    }

    return (
        <span
            aria-hidden="true"
            className="flex h-9 w-9 items-center justify-center rounded-md bg-[var(--brand)] text-sm font-semibold text-[var(--brand-foreground)]"
        >
            {tenant.name?.charAt(0).toUpperCase()}
        </span>
    );
}

export default function BlogLayout({ children }) {
    const { tenant } = usePage().props;

    return (
        <div className="flex min-h-screen flex-col bg-white text-zinc-900" style={brandStyle(tenant)}>
            <a
                href="#content"
                className="sr-only rounded-md bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-[var(--brand-foreground)] focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50"
            >
                Skip to content
            </a>

            <div aria-hidden="true" className="h-1 bg-[var(--brand)]" />

            <header className="border-b border-zinc-200">
                <div className="mx-auto flex h-16 max-w-3xl items-center px-4 sm:px-6">
                    <Link
                        href={route('tenant.home')}
                        className="flex cursor-pointer items-center gap-3 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2"
                    >
                        <BlogMark tenant={tenant} />
                        <span className="text-lg font-semibold tracking-tight">{tenant.name}</span>
                    </Link>
                </div>
            </header>

            <main id="content" className="mx-auto w-full max-w-3xl flex-1 px-4 py-12 sm:px-6">
                {children}
            </main>

            <footer className="border-t border-zinc-200">
                <div className="mx-auto flex max-w-3xl items-center gap-2 px-4 py-6 text-sm text-zinc-600 sm:px-6">
                    <span aria-hidden="true" className="h-2 w-2 rounded-full bg-[var(--brand)]" />
                    © {new Date().getFullYear()} {tenant.name}
                </div>
            </footer>
        </div>
    );
}
