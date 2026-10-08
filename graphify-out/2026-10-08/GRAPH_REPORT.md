# Graph Report - vitapictura  (2026-09-18)

## Corpus Check
- 50 files · ~223,795 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 785 nodes · 1777 edges · 72 communities (42 shown, 27 thin omitted)
- Extraction: 94% EXTRACTED · 6% INFERRED · 0% AMBIGUOUS · INFERRED: 106 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `d1f94966`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- DB
- D
- index-BcictjKS.js
- store/src/App.vue
- index-B-HWnFEG.js
- .get
- ro
- Controller
- R
- jr
- s
- D
- oo
- ii
- G
- ti
- admin/package.json
- store/package.json
- CustomerAuth
- G
- Checkout
- ye
- rr
- ri
- Catalog
- Vt
- Addresses
- admin/src/App.vue
- ce
- j
- ul
- Cart
- Payments.php
- pi
- _i
- Route
- Tahap 12 — Kesiapan migrasi dan peluncuran
- Tahap 2 — Data pelanggan dan lokasi
- qs
- $t
- fi
- Vita Pictura
- Configurator
- Orders
- Tahap 3 — Katalog
- ol
- gr
- Cn
- Tahap 4 — Login pelanggan dengan Google
- Gn
- L
- sn
- migrate_legacy_catalog.php
- migrate_legacy_product_images.php
- Env
- init.php
- deploy-api-apache.md
- stage-10-orders.md
- stage-11-admin-cs.md
- stage-1-foundation.md
- stage-5-addresses.md
- stage-6-configurator-upload.md
- stage-7-cart.md
- stage-8-checkout.md
- stage-9-payments.md
- fo
- jo
- Bn
- Tr

## God Nodes (most connected - your core abstractions)
1. `Controller` - 42 edges
2. `DB` - 30 edges
3. `oo()` - 29 edges
4. `ro` - 25 edges
5. `R()` - 24 edges
6. `D()` - 24 edges
7. `CustomerAuth` - 23 edges
8. `io()` - 23 edges
9. `L()` - 22 edges
10. `G()` - 21 edges

## Surprising Connections (you probably didn't know these)
- `He()` --indirect_call--> `Ds()`  [INFERRED]
  public/store/assets/index-BcictjKS.js → public/admin/assets/index-B-HWnFEG.js
- `fo()` --indirect_call--> `R()`  [INFERRED]
  public/store/assets/index-BcictjKS.js → public/admin/assets/index-B-HWnFEG.js
- `Ae()` --indirect_call--> `zr()`  [INFERRED]
  public/admin/assets/index-B-HWnFEG.js → public/store/assets/index-BcictjKS.js
- `ei()` --indirect_call--> `kr()`  [INFERRED]
  public/admin/assets/index-B-HWnFEG.js → public/store/assets/index-BcictjKS.js
- `ii()` --indirect_call--> `Kt()`  [INFERRED]
  public/admin/assets/index-B-HWnFEG.js → public/store/assets/index-BcictjKS.js

## Import Cycles
- None detected.

## Communities (72 total, 27 thin omitted)

### Community 0 - "DB"
Cohesion: 0.06
Nodes (5): DBC, Auth, Orders, DB, AdminAuth

### Community 1 - "D"
Cohesion: 0.09
Nodes (25): Ae(), ai(), br(), Bs(), D(), ei(), Hs(), _i() (+17 more)

### Community 2 - "index-BcictjKS.js"
Cohesion: 0.05
Nodes (30): ae, co, Dn, ec, el, Er, Fl, Fn (+22 more)

### Community 3 - "store/src/App.vue"
Cohesion: 0.09
Nodes (29): addCart(), addressBook, addresses, addressForm, cart, cartOpen, catalog, customer (+21 more)

### Community 4 - "index-B-HWnFEG.js"
Cohesion: 0.06
Nodes (24): as, bl, Ce, Cs, el, En, go, In (+16 more)

### Community 5 - ".get"
Cohesion: 0.13
Nodes (20): ai, at(), br, cr(), es, He(), je(), le() (+12 more)

### Community 6 - "ro"
Cohesion: 0.13
Nodes (24): An(), bi(), ds(), $e(), eo, fo(), E(), hi (+16 more)

### Community 7 - "Controller"
Cohesion: 0.13
Nodes (4): Base, Health, Midtrans, Controller

### Community 8 - "R"
Cohesion: 0.19
Nodes (23): ao(), cr, es(), Fe(), Gs(), Jn(), Kn(), L() (+15 more)

### Community 9 - "jr"
Cohesion: 0.13
Nodes (21): Be(), bt(), cs(), di, en(), Ge(), gi(), hs() (+13 more)

### Community 10 - "s"
Cohesion: 0.13
Nodes (22): an(), ar(), dn(), dr(), Ds(), Et(), gi, hr() (+14 more)

### Community 11 - "D"
Cohesion: 0.19
Nodes (22): bo(), Bs(), ct, D(), Ft(), Gs(), kr(), Kt() (+14 more)

### Community 12 - "oo"
Cohesion: 0.15
Nodes (21): bo(), bt, eo, ft(), Ge(), io(), te(), jr() (+13 more)

### Community 13 - "ii"
Cohesion: 0.17
Nodes (14): er(), fn(), ho(), ii(), je(), ln(), Ne(), Ns() (+6 more)

### Community 14 - "G"
Cohesion: 0.16
Nodes (19): Cl(), cn(), ct(), Do(), Ee(), G(), gl(), He() (+11 more)

### Community 15 - "ti"
Cohesion: 0.14
Nodes (3): dr, gn(), ti

### Community 16 - "admin/package.json"
Cohesion: 0.12
Nodes (16): dependencies, vue, devDependencies, vite, @vitejs/plugin-vue, vite, @vitejs/plugin-vue, vue (+8 more)

### Community 17 - "store/package.json"
Cohesion: 0.12
Nodes (16): dependencies, vue, devDependencies, vite, @vitejs/plugin-vue, vite, @vitejs/plugin-vue, vue (+8 more)

### Community 19 - "G"
Cohesion: 0.30
Nodes (15): ee(), ei(), fs(), G(), ht(), Jn(), K(), ki() (+7 more)

### Community 21 - "ye"
Cohesion: 0.19
Nodes (13): Nn(), nr, bl(), Et(), kn(), ml(), ol(), rl() (+5 more)

### Community 22 - "rr"
Cohesion: 0.18
Nodes (8): di, fr(), Os(), rr, to(), wo(), wt, Xs()

### Community 23 - "ri"
Cohesion: 0.18
Nodes (3): ri, rt, Sn()

### Community 25 - "Vt"
Cohesion: 0.20
Nodes (8): ar(), hr(), ii(), Nr, oi(), pn(), ri(), Vt()

### Community 27 - "admin/src/App.vue"
Cohesion: 0.29
Nodes (8): api(), error, form, loadOrders(), login(), orders, update(), user

### Community 28 - "ce"
Cohesion: 0.24
Nodes (8): ao(), ce(), ci(), fr, gr, Ls(), Xt(), zt()

### Community 29 - "j"
Cohesion: 0.36
Nodes (8): ci, co(), j(), ms(), Pr(), qn(), rs(), xo()

### Community 30 - "ul"
Cohesion: 0.25
Nodes (8): fl(), cl(), dl(), gl(), hl(), it(), ll(), ul()

### Community 33 - "pi"
Cohesion: 0.33
Nodes (7): Fi(), Ir(), is(), ji(), pi, po, to

### Community 34 - "_i"
Cohesion: 0.29
Nodes (7): Do(), _i(), Jt, nn(), Os(), Ur(), xi()

### Community 36 - "Tahap 12 — Kesiapan migrasi dan peluncuran"
Cohesion: 0.33
Nodes (5): Kondisi yang masih menghalangi peluncuran dari workspace ini, Prosedur staging, Status workspace, Syarat cutover produksi, Tahap 12 — Kesiapan migrasi dan peluncuran

### Community 37 - "Tahap 2 — Data pelanggan dan lokasi"
Cohesion: 0.33
Nodes (5): Batasan tahap ini, Cara memakai, Entitas tahap ini, Keputusan yang dikunci, Tahap 2 — Data pelanggan dan lokasi

### Community 38 - "qs"
Cohesion: 0.33
Nodes (6): bi, Gt(), hi, or, qs(), xn()

### Community 39 - "$t"
Cohesion: 0.33
Nodes (6): Bn, Fs(), nl(), $t(), Ti(), On()

### Community 41 - "Vita Pictura"
Cohesion: 0.33
Nodes (5): Database lokal, Konfigurasi API, Menjalankan frontend, Struktur, Vita Pictura

### Community 44 - "Tahap 3 — Katalog"
Cohesion: 0.40
Nodes (4): Batasan, Pemetaan ABFLab yang akan dieksekusi pada database salinan, Tahap 3 — Katalog, Yang telah tersedia

### Community 45 - "ol"
Cohesion: 0.40
Nodes (5): al(), dl(), il(), nt(), ol()

### Community 46 - "gr"
Cohesion: 0.40
Nodes (5): gr(), Ki(), Ko(), ps(), Ut()

### Community 47 - "Cn"
Cohesion: 0.80
Nodes (5): Cn(), rn(), us(), wo(), yt()

### Community 48 - "Tahap 4 — Login pelanggan dengan Google"
Cohesion: 0.50
Nodes (3): Konfigurasi yang diperlukan, Kontrak API, Tahap 4 — Login pelanggan dengan Google

### Community 49 - "Gn"
Cohesion: 0.50
Nodes (4): Gn(), hn(), mn(), rn()

### Community 50 - "L"
Cohesion: 0.50
Nodes (4): Hn(), L(), no, qi()

### Community 51 - "sn"
Cohesion: 0.50
Nodes (4): lo(), sn(), Ts(), vn()

## Knowledge Gaps
- **127 isolated node(s):** `name`, `private`, `version`, `type`, `dev` (+122 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 232 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **27 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `gn()` connect `ti` to `index-BcictjKS.js`, `index-B-HWnFEG.js`?**
  _High betweenness centrality (0.052) - this node is a cross-community bridge._
- **Why does `Sn()` connect `ri` to `s`, `index-BcictjKS.js`, `index-B-HWnFEG.js`?**
  _High betweenness centrality (0.052) - this node is a cross-community bridge._
- **Why does `Controller` connect `Controller` to `DB`, `Payments.php`, `Route`, `Configurator`, `Orders`, `CustomerAuth`, `Checkout`, `Catalog`, `Addresses`, `Cart`?**
  _High betweenness centrality (0.025) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `ro` (e.g. with `fo()` and `Xe()`) actually correct?**
  _`ro` has 2 INFERRED edges - model-reasoned connections that need verification._
- **What connects `name`, `private`, `version` to the rest of the system?**
  _127 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `DB` be split into smaller, more focused modules?**
  _Cohesion score 0.05851063829787234 - nodes in this community are weakly interconnected._
- **Should `D` be split into smaller, more focused modules?**
  _Cohesion score 0.08717948717948718 - nodes in this community are weakly interconnected._