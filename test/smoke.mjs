/* =============================================================
   smoke.mjs - first Inertia Agent Kit milestone.
   Builds a throwaway Laravel + Inertia + React fixture and proves:
   init, resource scaffold, generated type imports, audit, feedback,
   and verification evidence.
   ============================================================= */
import {
  existsSync,
  mkdirSync,
  mkdtempSync,
  readFileSync,
  rmSync,
  writeFileSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { main } from "../src/iak.mjs";

let pass = 0;
let fail = 0;

const ok = (condition, label) => {
  if (condition) {
    pass++;
    console.log(`  ok   ${label}`);
  } else {
    fail++;
    console.log(`  FAIL ${label}`);
  }
};

const readJson = (path) => JSON.parse(readFileSync(path, "utf8"));

const target = mkdtempSync(join(tmpdir(), "iak-smoke-"));
mkdirSync(join(target, "resources/css"), { recursive: true });
mkdirSync(join(target, "routes"), { recursive: true });
writeFileSync(join(target, "artisan"), "");
writeFileSync(join(target, "composer.json"), JSON.stringify({
  require: {
    "laravel/framework": "^12.0",
    "inertiajs/inertia-laravel": "^2.0",
  },
}));
writeFileSync(join(target, "package.json"), JSON.stringify({
  name: "iak-fixture",
  dependencies: {
    "@inertiajs/react": "^2.0.0",
    react: "^19.0.0",
  },
}));
writeFileSync(join(target, "resources/css/app.css"), '@import "tailwindcss";');

const originalExit = process.exit;
let lastExit = 0;
process.exit = (code) => { lastExit = code || 0; };
const run = (args) => {
  lastExit = 0;
  main([...args, "--target", target, "--json"]);
  return lastExit;
};

run(["init", "--apply"]);
ok(existsSync(join(target, "iak.config.json")), "init writes iak.config.json");
ok(existsSync(join(target, ".iak/manifest/iak.manifest.v1.json")), "init writes manifest");
ok(existsSync(join(target, ".iak/rules/frontend.md")), "init writes agent-facing rules");
ok(existsSync(join(target, "resources/js/types/generated/index.d.ts")), "init reserves generated types");
ok(existsSync(join(target, "resources/css/iak/tokens.css")), "init writes token primitives");

const config = readJson(join(target, "iak.config.json"));
ok(config.project.adapter === "laravel-inertia-react", "init detects Inertia React adapter");

ok(run(["new", "resource", "vehicles", "--apply"]) === 0, "resource scaffold command succeeds");
for (const page of ["index", "show", "create", "edit"]) {
  ok(existsSync(join(target, `resources/js/pages/vehicles/${page}.tsx`)), `resource creates ${page} page`);
}
ok(existsSync(join(target, "resources/js/features/vehicles/vehicle-table.tsx")), "resource creates table feature");
ok(existsSync(join(target, "resources/js/features/vehicles/vehicle-table.stories.tsx")), "resource creates table story");
ok(existsSync(join(target, "resources/js/features/vehicles/vehicle-form.tsx")), "resource creates form feature");
ok(existsSync(join(target, "resources/js/features/vehicles/vehicle-form.stories.tsx")), "resource creates form story");
ok(!existsSync(join(target, "resources/js/queries")), "resource does not create global queries folder");
ok(!existsSync(join(target, "resources/js/forms")), "resource does not create global forms folder");
ok(!existsSync(join(target, "resources/js/hooks")), "resource does not create global hooks folder");

const featureTypes = readFileSync(join(target, "resources/js/features/vehicles/vehicle.types.ts"), "utf8");
ok(featureTypes.includes("import type { App } from '@/types/generated/vehicles'"), "feature types import generated backend contracts");

ok(run(["audit", "--run-id", "run_clean"]) === 0, "audit passes on generated scaffold");
ok(existsSync(join(target, ".iak/runs/run_clean/audit.json")), "audit writes JSON artifact");

writeFileSync(
  join(target, "resources/js/features/vehicles/bad-card.tsx"),
  'export function BadCard() { return <div className="p-[7px]" style={{ color: "#ffffff" }} /> }\n',
);
ok(run(["audit", "--run-id", "run_bad"]) === 1, "audit catches deliberate style violations");
const failedAudit = readJson(join(target, ".iak/runs/run_bad/audit.json"));
ok(failedAudit.status === "failed" && failedAudit.violations.length >= 2, "audit artifact includes violations");
rmSync(join(target, "resources/js/features/vehicles/bad-card.tsx"));

ok(run(["feedback", "create", "--id", "fbk_test", "--message", "Reuse the standard vehicle table pattern.", "--route", "vehicles.index"]) === 0, "feedback create works");
ok(run(["feedback", "list"]) === 0, "feedback list works");
ok(run(["feedback", "show", "fbk_test"]) === 0, "feedback show works");
ok(run(["verify", "--run-id", "run_blocked"]) === 1, "verify blocks on unresolved feedback");

ok(run(["feedback", "resolve", "fbk_test", "--summary", "Vehicle table pattern accepted.", "--evidence", ".iak/runs/run_clean/audit.json"]) === 0, "feedback resolve requires evidence and works");

ok(run(["verify", "--run-id", "run_acceptance"]) === 0, "verify passes after feedback resolution");
const verify = readJson(join(target, ".iak/runs/run_acceptance/verify.json"));
ok(verify.status === "passed", "verify artifact status is passed");
ok(verify.browser.screenshots[0].path.endsWith(".png"), "verify writes screenshot metadata");
ok(existsSync(join(target, verify.browser.screenshots[0].path)), "verify writes screenshot artifact");

process.exit = originalExit;
rmSync(target, { recursive: true, force: true });
console.log(`\n  ${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
