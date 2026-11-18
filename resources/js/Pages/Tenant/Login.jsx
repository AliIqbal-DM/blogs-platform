import { brandButtonClass } from '@/brand';
import InputError from '@/Components/InputError';
import WriterLayout from '@/Layouts/WriterLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

const inputClass =
    'mt-1 block w-full rounded-md border-zinc-300 shadow-sm focus:border-[var(--brand)] focus:ring-[var(--brand)]';

export default function Login({ status }) {
    const { tenant } = usePage().props;
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('tenant.login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <WriterLayout>
            <Head title={`Writer log in · ${tenant.name}`} />

            <div className="mx-auto w-full max-w-sm">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Writer log in
                </h1>
                <p className="mt-1 text-sm text-zinc-600">
                    Sign in to write for {tenant.name}.
                </p>

                {status && (
                    <div className="mt-4 text-sm font-medium text-green-700">
                        {status}
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="mt-6 space-y-5 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm"
                >
                    <div>
                        <label htmlFor="email" className="block text-sm font-medium text-zinc-700">
                            Email
                        </label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className={inputClass}
                            autoComplete="username"
                            autoFocus
                            required
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div>
                        <label htmlFor="password" className="block text-sm font-medium text-zinc-700">
                            Password
                        </label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className={inputClass}
                            autoComplete="current-password"
                            required
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <label className="flex cursor-pointer items-center">
                        <input
                            type="checkbox"
                            name="remember"
                            checked={data.remember}
                            className="rounded border-zinc-300 text-[var(--brand)] shadow-sm focus:ring-[var(--brand)]"
                            onChange={(e) => setData('remember', e.target.checked)}
                        />
                        <span className="ms-2 text-sm text-zinc-600">Remember me</span>
                    </label>

                    <button
                        type="submit"
                        disabled={processing}
                        className={`${brandButtonClass} w-full py-2.5`}
                    >
                        {processing ? 'Logging in…' : 'Log in'}
                    </button>
                </form>
            </div>
        </WriterLayout>
    );
}
