const v = (n) => `rgb(var(--${n}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: ['./resources/views/**/*.blade.php', './resources/js/**/*.{ts,tsx}'],
    darkMode: ['class', '[data-theme="dark"]'],
    theme: {
        extend: {
            fontFamily: {
                display: ['"Noto Sans"', 'system-ui', 'Segoe UI', 'sans-serif'],
                sans: ['"Noto Sans"', 'system-ui', 'Segoe UI', 'sans-serif'],
            },
            colors: {
                bg: v('bg'), surface: v('surface'), sunken: v('sunken'), line: v('line'), ink: v('ink'), mute: v('mute'),
                navy: v('navy'), brand: v('brand'), brandsoft: v('brandsoft'), teal: v('teal'), tealsoft: v('tealsoft'), sky: v('sky'),
                ok: v('ok'), oksoft: v('oksoft'), warn: v('warn'), warnsoft: v('warnsoft'),
                bad: v('bad'), badsoft: v('badsoft'), info: v('info'), infosoft: v('infosoft'), side: v('side'), sideink: v('sideink'),
            },
            borderRadius: { xl: '12px', lg: '10px' },
            boxShadow: { card: '0 1px 2px rgb(16 42 67 / .06), 0 1px 1px rgb(16 42 67 / .03)', pop: '0 12px 32px rgb(16 42 67 / .18)' },
        },
    },
    plugins: [],
};
