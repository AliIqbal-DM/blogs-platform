import { brandStyle } from '@/brand';
import { Link, usePage } from '@inertiajs/react';

function TenantMark({ tenant }) {
    if (tenant.logo_url) {
        return (
            <img
                src={tenant.logo_url}
                alt={`${tenant.name} logo`}
                className="h-8 w-8 rounded-md object-cover ring-1 ring-zinc-200"
            />
        );
    }

    return (
        <span
            aria-hidden="true"
            className="flex h-8 w-8 items-center justify-center rounded-md bg-[var(--brand)] text-sm font-semibold text-[var(--brand-foreground)]"
        >
            {tenant.name?.charAt(0).toUpperCase()}
        </span>
    );
}

export default function WriterLayout({ children }) {
    const { tenant, auth, flash } = usePage().props;

    return (
        <div className="min-h-screen bg-zinc-50 text-zinc-900" style={brandStyle(tenant)}>
            <div aria-hidden="true" className="h-1 bg-[var(--brand)]" />
            <header className="border-b border-zinc-200 bg-white">
                <div className="mx-auto flex h-16 max-w-5xl items-center justify-between px-4 sm:px-6">
                    <Link
                        href={route(auth.user ? 'tenant.dashboard' : 'tenant.login')}
                        className="flex items-center gap-3 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2"
                    >
                        <TenantMark tenant={tenant} />
                        <span className="text-base font-semibold tracking-tight">
                            {tenant.name}
                        </span>
                    </Link>

                    {auth.user && (
                        <div className="flex items-center gap-4">
                            <Link
                                href={route('tenant.dashboard.posts.index')}
                                aria-current={route().current('tenant.dashboard.posts.*') ? 'page' : undefined}
                                className="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium text-zinc-700 transition-colors duration-200 hover:bg-zinc-100 hover:text-zinc-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] aria-[current=page]:text-zinc-900 aria-[current=page]:underline aria-[current=page]:decoration-[var(--brand)] aria-[current=page]:decoration-2 aria-[current=page]:underline-offset-8"
                            >
                                Posts
                            </Link>
                            <span className="hidden text-sm text-zinc-600 sm:inline">
                                {auth.user.name}
                            </span>
                            <Link
                                href={route('tenant.logout')}
                                method="post"
                                as="button"
                                className="cursor-pointer rounded-md px-3 py-1.5 text-sm font-medium text-zinc-700 transition-colors duration-200 hover:bg-zinc-100 hover:text-zinc-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]"
                            >
                                Log out
                            </Link>
                        </div>
                    )}
                </div>
            </header>

            <main className="mx-auto max-w-5xl px-4 py-10 sm:px-6">
                {flash?.success && (
                    <div
                        role="status"
                        className="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                    >
                        {flash.success}
                    </div>
                )}
                {children}
            </main>
        </div>
    );
}
