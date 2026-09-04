/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        // Enterprise primary — a calibrated, high-recognition professional blue.
        primary: {
          50:'#EFF5FF',100:'#DBE8FE',200:'#BFD7FE',300:'#93BBFD',400:'#609AFA',
          500:'#2F80ED',600:'#1D6FE0',700:'#1857BC',800:'#164B9C',900:'#143C7A',
        },
        // Accent — used sparingly for emphasis (active nav bar, highlights).
        accent: {
          50:'#EEF2FF',100:'#E0E7FF',200:'#C7D2FE',300:'#A5B4FC',400:'#818CF8',
          500:'#6366F1',600:'#4F46E5',700:'#4338CA',800:'#3730A3',900:'#312E81',
        },
        surface: {
          DEFAULT:'#FFFFFF', muted:'#F5F7FA', subtle:'#EDF1F6',
          dark:{ DEFAULT:'#0D1117', muted:'#161B22', subtle:'#20262E' },
        },
        ink: {
          DEFAULT:'#101828', muted:'#475467', subtle:'#8A94A6',
          dark:{ DEFAULT:'#E6EAF0', muted:'#9AA4B2', subtle:'#68727F' },
        },
        line: {
          DEFAULT:'#E4E8EF', strong:'#D3D9E2',
          dark:{ DEFAULT:'#252B34', strong:'#323A45' },
        },
      },
      fontFamily: {
        sans: ['Inter','system-ui','-apple-system','Segoe UI','Roboto','sans-serif'],
      },
      fontSize: {
        '2xs': ['0.6875rem', { lineHeight: '0.875rem' }], // 11px — micro labels / table headers
      },
      boxShadow: {
        card:  '0 1px 2px 0 rgb(16 24 40 / 0.05), 0 1px 3px 0 rgb(16 24 40 / 0.04)',
        panel: '0 1px 3px 0 rgb(16 24 40 / 0.06), 0 4px 12px -2px rgb(16 24 40 / 0.06)',
        pop:   '0 4px 6px -1px rgb(16 24 40 / 0.08), 0 12px 28px -6px rgb(16 24 40 / 0.16)',
        focus: '0 0 0 3px rgb(47 128 237 / 0.28)',
      },
      height:  { 13: '3.25rem' },
      spacing: { 13: '3.25rem' },
      maxWidth: { content: '1560px' },
    },
  },
  plugins: [require('@tailwindcss/forms')],
};
