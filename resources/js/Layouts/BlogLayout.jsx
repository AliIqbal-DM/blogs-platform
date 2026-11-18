import { Link, usePage } from '@inertiajs/react';

function BlogMark({ tenant }) {
    if (tenant.logo_url) {
        return (
            <img
                src={tenant.logo_url}
                alt={`${tenant.name} logo`}
                className="h-9 w-9 rounded-md object-cover"
            />
        );
    }

    return (
        <span
            aria-hidden="true"
            className="flex h-9 w-9 items-center justify-center rounded-md bg-zinc-900 text-sm font-semibold text-white"
        >
            {tenant.name?.charAt(0).toUpperCase()}
        </span>
    );
}

export default function BlogLayout({ children }) {
    const { tenant } = usePage().props;

    return (
        <div className="flex min-h-screen flex-col bg-white text-zinc-900">
            <header className="border-b border-zinc-200">
                <div className="mx-auto flex h-16 max-w-3xl items-center px-4 sm:px-6">
                    <Link
                        href={route('tenant.home')}
                        className="flex items-center gap-3 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-zinc-900 focus-visible:ring-offset-2"
                    >
                        <BlogMark tenant={tenant} />
                        <span className="text-lg font-semibold tracking-tight">{tenant.name}</span>
                    </Link>
                </div>
            </header>

            <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-12 sm:px-6">{children}</main>

            <footer className="border-t border-zinc-200">
                <div className="mx-auto max-w-3xl px-4 py-6 text-sm text-zinc-600 sm:px-6">
                    © {new Date().getFullYear()} {tenant.name}
                </div>
            </footer>
        </div>
    );
}
