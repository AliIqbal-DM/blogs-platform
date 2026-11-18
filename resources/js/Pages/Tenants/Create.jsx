import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const HEX_COLOR = /^#[0-9a-fA-F]{6}$/;

export default function Create({ defaultBrandColor }) {
    const { data, setData, post, processing, errors, progress } = useForm({
        name: '',
        domain: '',
        brand_color: defaultBrandColor,
        logo: null,
    });

    const [logoPreview, setLogoPreview] = useState(null);

    useEffect(() => {
        if (!data.logo) {
            setLogoPreview(null);
            return;
        }

        const url = URL.createObjectURL(data.logo);
        setLogoPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [data.logo]);

    const colorIsValid = HEX_COLOR.test(data.brand_color);

    const submit = (e) => {
        e.preventDefault();
        post(route('tenants.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    New blog
                </h2>
            }
        >
            <Head title="New blog" />

            <div className="py-12">
                <div className="mx-auto max-w-2xl sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg"
                        noValidate
                    >
                        <div>
                            <InputLabel htmlFor="name" value="Name" />
                            <TextInput
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                maxLength={100}
                                isFocused
                                required
                                onChange={(e) => setData('name', e.target.value)}
                            />
                            <p className="mt-1 text-xs text-gray-600">
                                The blog id (used for its database) is generated from the name.
                            </p>
                            <InputError message={errors.name} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="domain" value="Domain" />
                            <TextInput
                                id="domain"
                                className="mt-1 block w-full"
                                value={data.domain}
                                placeholder="acme.blogs.test"
                                autoComplete="off"
                                autoCapitalize="none"
                                spellCheck={false}
                                required
                                onChange={(e) => setData('domain', e.target.value.trim().toLowerCase())}
                            />
                            <InputError message={errors.domain} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="brand_color" value="Brand color" />
                            <div className="mt-1 flex items-center gap-3">
                                <input
                                    type="color"
                                    aria-label="Pick brand color"
                                    className="h-10 w-14 cursor-pointer rounded-md border border-gray-300 bg-white p-1"
                                    value={colorIsValid ? data.brand_color : defaultBrandColor}
                                    onChange={(e) => setData('brand_color', e.target.value)}
                                />
                                <TextInput
                                    id="brand_color"
                                    className="block w-36 font-mono"
                                    value={data.brand_color}
                                    maxLength={7}
                                    onChange={(e) => setData('brand_color', e.target.value)}
                                />
                            </div>
                            {!colorIsValid && !errors.brand_color && (
                                <p className="mt-2 text-sm text-gray-600">
                                    Use the format #rrggbb.
                                </p>
                            )}
                            <InputError message={errors.brand_color} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="logo" value="Logo (optional)" />
                            <div className="mt-1 flex items-center gap-4">
                                {logoPreview && (
                                    <img
                                        src={logoPreview}
                                        alt="Logo preview"
                                        className="h-14 w-14 rounded-md border border-gray-200 object-contain"
                                    />
                                )}
                                <input
                                    id="logo"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    className="block w-full cursor-pointer text-sm text-gray-700 file:mr-4 file:cursor-pointer file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200"
                                    onChange={(e) => setData('logo', e.target.files[0] ?? null)}
                                />
                            </div>
                            <p className="mt-1 text-xs text-gray-600">
                                PNG, JPG or WebP, up to 2 MB.
                            </p>
                            {progress && (
                                <progress
                                    value={progress.percentage}
                                    max="100"
                                    className="mt-2 w-full"
                                >
                                    {progress.percentage}%
                                </progress>
                            )}
                            <InputError message={errors.logo} className="mt-2" />
                        </div>

                        <div className="flex items-center justify-end gap-4">
                            <Link
                                href={route('tenants.index')}
                                className="cursor-pointer rounded-md text-sm text-gray-600 underline-offset-2 transition-colors duration-150 hover:text-gray-900 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            >
                                Cancel
                            </Link>
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Creating…' : 'Create blog'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
