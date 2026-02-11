// MongoDB Initialization Script
// Creates users and populates with sensitive mock data for CVE-2025-14847 PoC

// Switch to admin database to create application user
db = db.getSiblingDB('admin');

// Create application user
db.createUser({
  user: "appuser",
  pwd: "AppPassword456!",
  roles: [
    { role: "readWrite", db: "secretdb" },
    { role: "readWrite", db: "customers" }
  ]
});

// Switch to secretdb
db = db.getSiblingDB('secretdb');

// Flag marker to help validate memory leak capture
const FLAG = "RAON2026{7c6255d17237634e4c132d5e8617386dc6f9d6604f485d2f6756c880eca85fd2}";

// Minimal: create a small collection containing only our FLAG
db.flags.drop();
db.createCollection('flags');
db.flags.insertOne({
  flag: FLAG,
  created_at: new Date()
});

// Heavily seed heap with the FLAG in different sizes (touch multiple allocator bins)
// Sizes roughly align with extras used by the PoC (4KB, 16KB, 64KB, 256KB)
(function seedFlagHeaps() {
  db.flag_heap_4k.drop();
  db.flag_heap_16k.drop();
  db.flag_heap_64k.drop();
  db.flag_heap_256k.drop();

  const repeat = (s, n) => Array(n + 1).join(s);

  // Build raonflag1/raonflag2 as exact 64-byte markers based on requirement:
  //  - raonflag1: + first part of FLAG to make exactly 64 chars
  //  - raonflag2: + remaining part of FLAG, pad with '_' to exactly 64 chars
  const prefix1 = "raonflag1:";
  const prefix2 = "raonflag2:";
  const take1 = Math.max(0, 64 - prefix1.length);
  const part1 = FLAG.slice(0, take1);
  const remain = FLAG.slice(take1);
  let RAONFLAG1 = prefix1 + part1;
  if (RAONFLAG1.length < 64) RAONFLAG1 = RAONFLAG1 + repeat("_", 64 - RAONFLAG1.length);
  let RAONFLAG2 = prefix2 + remain;
  if (RAONFLAG2.length < 64) RAONFLAG2 = RAONFLAG2 + repeat("_", 64 - RAONFLAG2.length);

  // Build payloads that contain the tokens repeatedly to maximize visibility
  function buildPayload(totalSize, fillChar) {
    const tokens = [RAONFLAG1, RAONFLAG2];
    let s = "";
    let i = 0;
    while (i < tokens.length || s.length + tokens[0].length + 1 <= totalSize) {
      const t = tokens[i % tokens.length] + "_";
      if (s.length + t.length > totalSize) break;
      s += t;
      i++;
    }
    if (s.length < totalSize) {
      s += repeat(fillChar, Math.max(0, totalSize - s.length));
    }
    return s.slice(0, totalSize);
  }

  const p4k   = buildPayload(4096,  "X");
  const p16k  = buildPayload(16000, "Y");
  const p64k  = buildPayload(64000, "Z");
  const p256k = buildPayload(256000,"W");

  // Insert batches to avoid huge single inserts
  function bulkInsert(collName, payload, count) {
    db.createCollection(collName);
    const coll = db.getCollection(collName);
    const batch = [];
    for (let i = 0; i < count; i++) {
      batch.push({
        i,
        canary: FLAG,
        payload,
        rf1: RAONFLAG1,
        rf2: RAONFLAG2,
        created_at: new Date()
      });
      if (batch.length === 500) {
        coll.insertMany(batch);
        batch.length = 0;
      }
    }
    if (batch.length) coll.insertMany(batch);
  }

  // Tune counts to be significant but not excessively heavy
  bulkInsert('flag_heap_4k',   p4k,   3000);
  bulkInsert('flag_heap_16k',  p16k,  1200);
  bulkInsert('flag_heap_64k',  p64k,   400);
  bulkInsert('flag_heap_256k', p256k,   80);
})();

// Also seed documents where the FLAG appears in the key name to reflect in parser paths
(function seedFlagKeys() {
  db.flag_keys.drop();
  db.createCollection('flag_keys');
  for (let i = 0; i < 1000; i++) {
    const k = FLAG + "_" + i;
    const doc = {};
    doc[k] = "val_" + i + "_" + FLAG;
    db.flag_keys.insertOne(doc);
  }
})();

// Seed initial ranking board data (raon1 ~ raon5)
(function seedRankings() {
  db.rankings.drop();
  db.createCollection('rankings');
  const now = new Date();
  const names = ['raon1','raon2','raon3','raon4','raon5'];
  const docs = names.map((n, i) => ({
    name: n,
    wins: i + 1, // 1,2,3,4,5로 시드
    lastWinAt: new Date(now.getTime() - i * 60000)
  }));
  db.rankings.insertMany(docs);
  db.rankings.createIndex({ name: 1 }, { unique: true });
  db.rankings.createIndex({ wins: -1, lastWinAt: -1 });
})();


