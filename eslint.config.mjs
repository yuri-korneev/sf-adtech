// eslint.config.mjs — ESLint v9 (flat config)
export default [
  // Игнорируем сгенерённые и внешние папки
  { ignores: ["node_modules/**", "public/build/**", "vendor/**", "storage/**"] },

  // Линтим только исходники фронтенда (браузерная среда)
  {
    files: ["resources/js/**/*.{js,mjs}"],
    languageOptions: {
      ecmaVersion: "latest",
      sourceType: "module",
      // объявляем браузерные глобалы, чтобы не было no-undef
      globals: {
        window: "readonly",
        document: "readonly",
        navigator: "readonly"
      }
    },
    rules: {
      "no-unused-vars": ["warn", { argsIgnorePattern: "^_" }]
    }
  }
];
