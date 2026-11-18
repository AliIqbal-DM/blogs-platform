const HEX_COLOR = /^#[0-9a-fA-F]{6}$/;

const DEFAULT_THEME = { brand: '#4f46e5', brand_foreground: '#ffffff' };

/**
 * CSS variables for the tenant's brand, set once at a layout root.
 * The server shares a validated color with a readable text color for it; anything else falls back to the default.
 */
export function brandStyle(tenant) {
    const theme = tenant?.theme;
    const { brand, brand_foreground } =
        HEX_COLOR.test(theme?.brand ?? '') && HEX_COLOR.test(theme?.brand_foreground ?? '')
            ? theme
            : DEFAULT_THEME;

    return { '--brand': brand, '--brand-foreground': brand_foreground };
}

/** The primary call to action: brand background with the matching readable text color. */
export const brandButtonClass =
    'inline-flex cursor-pointer items-center justify-center rounded-md bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-[var(--brand-foreground)] shadow-sm transition-opacity duration-200 hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none';

/** Text links: dark text for contrast on white, with the brand color as the underline. */
export const brandLinkClass =
    'cursor-pointer rounded-sm underline decoration-transparent decoration-2 underline-offset-4 transition-colors duration-200 hover:decoration-[var(--brand)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 motion-reduce:transition-none';
