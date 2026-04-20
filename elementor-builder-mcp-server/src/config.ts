import { readFileSync } from "fs";
import { resolve } from "path";

export interface Config {
  wordpressUrl: string;
  username: string;
  appPassword: string;
  mcpEndpoint: string;
  screenshot: {
    viewportWidth: number;
    viewportHeight: number;
    timeout: number;
  };
  sessionTimeoutMinutes: number;
}

export function loadConfig(): Config {
  // Load .env file manually (no dotenv dependency)
  const envPath = resolve(import.meta.dirname, "../.env");
  let envVars: Record<string, string> = {};
  try {
    const envContent = readFileSync(envPath, "utf-8");
    for (const line of envContent.split("\n")) {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith("#")) continue;
      const eqIndex = trimmed.indexOf("=");
      if (eqIndex === -1) continue;
      const key = trimmed.slice(0, eqIndex).trim();
      const value = trimmed.slice(eqIndex + 1).trim();
      envVars[key] = value;
    }
  } catch {
    // .env not found — rely on process.env
  }

  const get = (key: string, fallback = ""): string =>
    process.env[key] ?? envVars[key] ?? fallback;

  const wordpressUrl = get("WORDPRESS_URL").replace(/\/+$/, "");
  if (!wordpressUrl) {
    throw new Error("WORDPRESS_URL is required in .env or environment.");
  }

  return {
    wordpressUrl,
    username: get("WORDPRESS_USERNAME"),
    appPassword: get("WORDPRESS_APP_PASSWORD"),
    mcpEndpoint:
      get("MCP_ENDPOINT") ||
      `${wordpressUrl}/wp-json/mcp/mcp-adapter-default-server`,
    screenshot: {
      viewportWidth: parseInt(get("SCREENSHOT_VIEWPORT_WIDTH", "1920"), 10),
      viewportHeight: parseInt(get("SCREENSHOT_VIEWPORT_HEIGHT", "1080"), 10),
      timeout: parseInt(get("SCREENSHOT_TIMEOUT", "15000"), 10),
    },
    sessionTimeoutMinutes: parseInt(
      get("SESSION_TIMEOUT_MINUTES", "30"),
      10
    ),
  };
}
