import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';

function PlusIcon() {
    return (
        <svg
            className="h-4 w-4"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 5v14M5 12h14" />
        </svg>
    );
}

function TenantLogo({ tenant }) {
    if (tenant.logo_url) {
        return (
            <img
                src={tenant.logo_url}
                alt={`${tenant.name} logo`}
                className="h-10 w-10 rounded-md border border-gray-200 bg-white object-contain"
            />
        );
    }

    return (
        <div
            className="flex h-10 w-10 items-center justify-center rounded-md text-sm font-semibold text-white"
            style={{ backgroundColor: tenant.brand_color }}
            aria-hidden="true"
        >
            {tenant.name.charAt(0).toUpperCase()}
        </div>
    );
}

function NewBlogButton() {
    return (
        <Link
            href={route('tenants.create')}
            className="inline-flex cursor-pointer items-center gap-2 rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition-colors duration-200 hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
        >
            <PlusIcon />
            New blog
        </Link>
    );
}

export default function Index({ tenants }) {
    const { flash } = usePage().props;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Blogs
                    </h2>
                    <NewBlogButton />
                </div>
            }
        >
            <Head title="Blogs" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                    {flash?.success && (
                        <div
                            role="status"
                            className="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                        >
                            {flash.success}
                        </div>
                    )}

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        {tenants.length === 0 ? (
                            <div className="p-10 text-center">
                                <p className="text-sm text-gray-600">
                                    No blogs yet. Create the first one to get started.
                                </p>
                                <div className="mt-4">
                                    <NewBlogButton />
                                </div>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th scope="col" className="px-6 py-3 text-left font-medium text-gray-600">
                                                Blog
                                            </th>
                                            <th scope="col" className="px-6 py-3 text-left font-medium text-gray-600">
                                                Domain
                                            </th>
                                            <th scope="col" className="px-6 py-3 text-right font-medium text-gray-600">
                                                Posts
                                            </th>
                                            <th scope="col" className="px-6 py-3 text-left font-medium text-gray-600">
                                                Brand color
                                            </th>
                                            <th scope="col" className="px-6 py-3 text-left font-medium text-gray-600">
                                                Created
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {tenants.map((tenant) => (
                                            <tr
                                                key={tenant.id}
                                                className="transition-colors duration-150 hover:bg-gray-50"
                                            >
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-3">
                                                        <TenantLogo tenant={tenant} />
                                                        <div>
                                                            <div className="font-medium text-gray-900">
                                                                {tenant.name}
                                                            </div>
                                                            <div className="text-xs text-gray-600">
                                                                {tenant.id}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4">
                                                    <ul className="space-y-1">
                                                        {tenant.domains.map((domain) => (
                                                            <li key={domain}>
                                                                <a
                                                                    href={`http://${domain}`}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="cursor-pointer text-indigo-600 underline-offset-2 transition-colors duration-150 hover:text-indigo-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                                                >
                                                                    {domain}
                                                                </a>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-right tabular-nums text-gray-700">
                                                    {tenant.post_count ?? (
                                                        <span title="This blog's database has no posts table yet">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="flex items-center gap-2">
                                                        <span
                                                            className="h-5 w-5 rounded border border-gray-300"
                                                            style={{ backgroundColor: tenant.brand_color }}
                                                            aria-hidden="true"
                                                        />
                                                        <span className="font-mono text-xs text-gray-700">
                                                            {tenant.brand_color}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="whitespace-nowrap px-6 py-4 text-gray-700">
                                                    {tenant.created_at}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
