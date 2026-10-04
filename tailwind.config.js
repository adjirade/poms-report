export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: [
                    '-apple-system',
                    'BlinkMacSystemFont',
                    '"SF Pro Text"',
                    '"SF Pro Display"',
                    'Inter',
                    '"Segoe UI"',
                    'Roboto',
                    'sans-serif',
                ],
            },
            borderRadius: {
                '4xl': '2rem',
                '5xl': '2.75rem',
            },
            boxShadow: {
                // Panel kaca: bayangan lembut + highlight tipis di tepi atas.
                glass: '0 18px 40px -22px rgba(6, 60, 36, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.85)',
                'glass-lg': '0 30px 60px -28px rgba(6, 60, 36, 0.55), inset 0 1px 0 rgba(255, 255, 255, 0.9)',
                // Kontrol mengambang (tombol/ikon).
                float: '0 10px 24px -12px rgba(6, 60, 36, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.35)',
                inner: 'inset 0 1px 2px rgba(15, 23, 42, 0.06)',
            },
            backdropBlur: {
                xs: '2px',
            },
            keyframes: {
                'aurora-drift': {
                    '0%, 100%': { transform: 'translate3d(0,0,0) scale(1)' },
                    '50%': { transform: 'translate3d(3%, -4%, 0) scale(1.08)' },
                },
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                'aurora-drift': 'aurora-drift 18s ease-in-out infinite',
                'fade-up': 'fade-up 0.4s ease-out both',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
}
