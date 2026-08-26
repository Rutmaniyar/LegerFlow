module.exports = {
  content: [
    './app/Views/**/*.php',
    './public/**/*.php',
    './public/**/*.js',
    './app/Core/**/*.php'
  ],
  theme: {
    extend: {
      colors: {
        ink: {
          50: '#f8fafc',
          100: '#eef2f7',
          200: '#d9e1ea',
          300: '#b8c5d2',
          400: '#8799aa',
          500: '#637487',
          600: '#48576a',
          700: '#344154',
          800: '#202b3a',
          900: '#111827',
          950: '#070b12'
        },
        brand: {
          50: 'rgb(var(--brand-50, 238 253 250) / <alpha-value>)',
          100: 'rgb(var(--brand-100, 207 248 241) / <alpha-value>)',
          200: 'rgb(var(--brand-200, 158 240 227) / <alpha-value>)',
          300: 'rgb(var(--brand-300, 94 223 204) / <alpha-value>)',
          400: 'rgb(var(--brand-400, 34 198 180) / <alpha-value>)',
          500: 'rgb(var(--brand-500, 14 163 148) / <alpha-value>)',
          600: 'rgb(var(--brand-600, 11 129 121) / <alpha-value>)',
          700: 'rgb(var(--brand-700, 13 103 97) / <alpha-value>)',
          800: 'rgb(var(--brand-800, 16 82 79) / <alpha-value>)',
          900: 'rgb(var(--brand-900, 18 63 62) / <alpha-value>)'
        },
        accent: {
          50: 'rgb(var(--accent-50, 245 243 255) / <alpha-value>)',
          100: 'rgb(var(--accent-100, 237 233 254) / <alpha-value>)',
          200: 'rgb(var(--accent-200, 221 214 254) / <alpha-value>)',
          300: 'rgb(var(--accent-300, 196 181 253) / <alpha-value>)',
          400: 'rgb(var(--accent-400, 167 139 250) / <alpha-value>)',
          500: 'rgb(var(--accent-500, 139 92 246) / <alpha-value>)',
          600: 'rgb(var(--accent-600, 124 58 237) / <alpha-value>)',
          700: 'rgb(var(--accent-700, 109 40 217) / <alpha-value>)',
          800: 'rgb(var(--accent-800, 91 33 182) / <alpha-value>)',
          900: 'rgb(var(--accent-900, 76 29 149) / <alpha-value>)'
        }
      },
      boxShadow: {
        soft: '0 18px 60px rgba(17, 24, 39, 0.08)',
        card: '0 10px 30px rgba(17, 24, 39, 0.06)'
      }
    }
  },
  plugins: []
};
