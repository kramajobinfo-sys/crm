/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: {
          50:'#E6F1FB',100:'#B5D4F4',200:'#85B7EB',300:'#5BA0E4',400:'#378ADD',
          500:'#1F73C9',600:'#185FA5',700:'#114780',800:'#0C447C',900:'#042C53',
        },
        surface: {
          DEFAULT:'#FFFFFF', muted:'#F4F6F9', subtle:'#EEF1F6',
          dark:{ DEFAULT:'#0F1419', muted:'#161B22', subtle:'#1F2937' },
        },
        ink: {
          DEFAULT:'#0F172A', muted:'#475569', subtle:'#94A3B8',
          dark:{ DEFAULT:'#F3F4F6', muted:'#9CA3AF', subtle:'#6B7280' },
        },
      },
      fontFamily: { sans: ['Inter','system-ui','sans-serif'] },
      boxShadow: { card: '0 1px 2px 0 rgb(0 0 0 / 0.04), 0 1px 3px 0 rgb(0 0 0 / 0.06)' },
      height: { 13: '3.25rem' },
    },
  },
  plugins: [require('@tailwindcss/forms')],
};
