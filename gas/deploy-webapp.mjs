import fs from "node:fs";
import path from "node:path";

const scriptId = process.env.GAS_SCRIPT_ID;
const deploymentId = process.env.GAS_DEPLOYMENT_ID;
const credentialsPath = path.join(process.env.HOME, ".clasprc.json");

if (!scriptId) {
  throw new Error("GAS_SCRIPT_ID is required.");
}

if (!deploymentId) {
  throw new Error("GAS_DEPLOYMENT_ID is required.");
}

const claspCredentials = JSON.parse(fs.readFileSync(credentialsPath, "utf8"));
const credentials = claspCredentials.tokens?.default || claspCredentials.token;

if (!credentials?.client_id || !credentials?.client_secret || !credentials?.refresh_token) {
  throw new Error("Invalid clasp credentials.");
}

const accessToken = await refreshAccessToken_(credentials);
const headers = {
  Authorization: `Bearer ${accessToken}`,
  "Content-Type": "application/json",
};

const files = readAppsScriptFiles_(process.cwd());
await updateProjectContent_(scriptId, files, headers);

const version = await createVersion_(scriptId, headers);
await updateDeployment_(scriptId, deploymentId, version.versionNumber, headers);

console.log(`Deployed Apps Script version ${version.versionNumber} to ${deploymentId}.`);

async function refreshAccessToken_(credentials) {
  const response = await fetch("https://oauth2.googleapis.com/token", {
    method: "POST",
    body: new URLSearchParams({
      client_id: credentials.client_id,
      client_secret: credentials.client_secret,
      refresh_token: credentials.refresh_token,
      grant_type: "refresh_token",
    }),
  });

  if (!response.ok) {
    throw new Error(`Failed to refresh OAuth token: ${response.status} ${await response.text()}`);
  }

  const payload = await response.json();
  return payload.access_token;
}

function readAppsScriptFiles_(directory) {
  const files = [
    {
      name: "appsscript",
      type: "JSON",
      source: fs.readFileSync(path.join(directory, "appsscript.json"), "utf8"),
    },
  ];

  fs.readdirSync(directory)
    .filter((fileName) => fileName.endsWith(".js"))
    .sort()
    .forEach((fileName) => {
      files.push({
        name: fileName.replace(/\.js$/, ""),
        type: "SERVER_JS",
        source: fs.readFileSync(path.join(directory, fileName), "utf8"),
      });
    });

  return files;
}

async function updateProjectContent_(scriptId, files, headers) {
  const response = await fetch(`https://script.googleapis.com/v1/projects/${scriptId}/content`, {
    method: "PUT",
    headers,
    body: JSON.stringify({ files }),
  });

  if (!response.ok) {
    throw new Error(`Failed to update Apps Script content: ${response.status} ${await response.text()}`);
  }
}

async function createVersion_(scriptId, headers) {
  const response = await fetch(`https://script.googleapis.com/v1/projects/${scriptId}/versions`, {
    method: "POST",
    headers,
    body: JSON.stringify({ description: `GitHub Actions ${process.env.GITHUB_SHA || "manual"}` }),
  });

  if (!response.ok) {
    throw new Error(`Failed to create Apps Script version: ${response.status} ${await response.text()}`);
  }

  return response.json();
}

async function updateDeployment_(scriptId, deploymentId, versionNumber, headers) {
  const response = await fetch(`https://script.googleapis.com/v1/projects/${scriptId}/deployments/${deploymentId}`, {
    method: "PUT",
    headers,
    body: JSON.stringify({
      deploymentConfig: {
        scriptId,
        versionNumber,
        manifestFileName: "appsscript",
        description: `GitHub Actions ${process.env.GITHUB_SHA || "manual"}`,
      },
    }),
  });

  if (!response.ok) {
    throw new Error(`Failed to update Apps Script deployment: ${response.status} ${await response.text()}`);
  }
}
