// eslint.config.mjs — ESLint v9 (flat config)
import js from "@eslint/js";

export default [
  // Игнорируем сгенерённые и внешние папки
  { ignores: ["node_modules/**", "public/build/**", "vendor/**", "storage/**"] },

  // Линтим только исходники фронтенда
  {
    files: ["resources/js/**/*.{js,mjs}"],
    languageOptions: { ecmaVersion: "latest", sourceType: "module" },
    rules: {
      ...js.configs.recommended.rules,
      "no-unused-vars": ["warn", { argsIgnorePattern: "^_" }]
    }
  }
];
