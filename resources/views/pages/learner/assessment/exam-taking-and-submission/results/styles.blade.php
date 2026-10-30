<style>
    .attempt-results { color: var(--ink); }
    .attempt-results a:focus-visible,
    .attempt-results button:focus-visible,
    .attempt-results summary:focus-visible { outline: 3px solid rgba(11, 102, 239, .35); outline-offset: 2px; }
    .ar-breadcrumbs { display:flex; align-items:center; gap:8px; margin-bottom:8px; color:#8593a9; font-size:11px; }
    .ar-breadcrumbs a:hover { color:var(--blue); }
    .ar-breadcrumbs span:last-child { color:#536783; }
    .ar-heading { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; margin-bottom:16px; }
    .ar-heading h1 { margin:0; font-size:24px; }
    .ar-heading p { margin:3px 0 8px; color:#526684; font-size:13px; }
    .ar-tags { display:flex; flex-wrap:wrap; gap:6px; }
    .ar-tag { display:inline-flex; align-items:center; gap:5px; min-height:24px; padding:3px 9px; border-radius:999px; color:#64758f; background:#f0f4f9; font-size:10px; }
    .ar-tag--blue { color:#1763d5; background:#eaf2ff; }
    .ar-actions { display:flex; flex:0 0 auto; gap:8px; padding-top:2px; }
    .ar-layout { display:grid; grid-template-columns:minmax(0,1fr) 310px; align-items:start; gap:15px; }
    .ar-main-column, .ar-side-column { display:grid; gap:14px; min-width:0; }
    .ar-side-column { position:sticky; top:82px; }
    .ar-card { overflow:hidden; border:1px solid var(--line); border-radius:13px; background:var(--card); box-shadow:var(--shadow); }
    .ar-summary { display:grid; grid-template-columns:154px minmax(0,1fr) 236px; align-items:center; gap:17px; padding:17px; }
    .ar-ring { width:142px; aspect-ratio:1; display:grid; place-items:center; margin:auto; position:relative; border-radius:50%; background:conic-gradient(var(--blue) 0 var(--ar-score-fill, 0%), #e6edf7 var(--ar-score-fill, 0%) 100%); }
    .ar-ring::before { content:""; position:absolute; inset:11px; border-radius:50%; background:#fff; }
    .ar-ring > div { position:relative; z-index:1; text-align:center; }
    .ar-ring strong, .ar-ring span { display:block; }
    .ar-ring strong { color:var(--ink); font-size:28px; line-height:1.1; }
    .ar-ring span { margin-top:3px; color:#687a96; font-size:12px; }
    .ar-score-copy h2 { margin:0 0 7px; font-size:19px; }
    .ar-score-line { margin-bottom:9px; color:var(--ink); font-size:26px; font-weight:800; line-height:1.15; letter-spacing:-.04em; }
    .ar-score-line small { color:#5d6f8b; font-size:16px; font-weight:500; letter-spacing:0; }
    .ar-score-note { padding:9px 11px; border-radius:9px; color:#526987; background:#eff5ff; font-size:11px; }
    .ar-score-note b { display:block; margin-bottom:2px; color:var(--blue); font-size:12px; }
    .ar-metrics { display:grid; gap:0; margin:0; padding:0 0 0 14px; border-left:1px solid var(--line); list-style:none; }
    .ar-metric { display:grid; grid-template-columns:23px minmax(0,1fr) auto; align-items:center; gap:7px; min-height:34px; border-bottom:1px solid #edf1f7; font-size:10px; }
    .ar-metric:last-child { border-bottom:0; }
    .ar-metric-icon { width:21px; height:21px; display:grid; place-items:center; border-radius:50%; color:var(--blue); background:#eaf2ff; }
    .ar-metric--good .ar-metric-icon { color:#11976c; background:#e6f8ef; }
    .ar-metric--wrong .ar-metric-icon { color:#d9435d; background:#fff0f2; }
    .ar-metric--muted .ar-metric-icon { color:#8390a4; background:#eef1f5; }
    .ar-metric-label { color:#65758d; }
    .ar-metric strong { color:var(--ink); font-size:10px; text-align:right; }
    .ar-section-title { display:flex; align-items:center; justify-content:space-between; gap:10px; margin:0; font-size:14px; }
    .ar-section-title small { color:#8190a7; font-size:10px; font-weight:500; }
    .ar-side-card { padding:15px; }
    .ar-side-card .ar-section-title { margin-bottom:13px; }
    .ar-part-list { display:grid; gap:10px; }
    .ar-part-row { display:grid; grid-template-columns:minmax(0,1fr) 76px 38px; align-items:center; gap:8px; min-width:0; font-size:10px; }
    .ar-part-name { overflow:hidden; color:#52647f; text-overflow:ellipsis; white-space:nowrap; }
    .ar-part-score { color:#52647f; font-size:10px; font-weight:700; text-align:right; }
    .ar-progress { height:8px; overflow:hidden; border-radius:999px; background:#e8edf4; }
    .ar-progress > span { display:block; height:100%; border-radius:inherit; background:#37bd70; }
    .ar-question-map { display:grid; grid-template-columns:repeat(10,minmax(0,1fr)); gap:5px; }
    .ar-map-number { min-width:0; aspect-ratio:1; display:grid; place-items:center; border:1px solid #dce7f2; border-radius:6px; color:#51637e; background:#f8fafc; font-size:10px; font-weight:700; }
    a.ar-map-number:hover { border-color:#8eb7fa; color:var(--blue); background:#f1f6ff; }
    .ar-map-number--available { border-color:#bde8cd; color:#31845a; background:#effaf3; }
    .ar-map-number--wrong { border-color:#ffcbd1; color:#ca4056; background:#fff0f2; }
    .ar-map-number--blank { border-color:#e4e8ef; color:#8c98a9; background:#f2f4f7; }
    .ar-map-number--review { border-color:#cadcf9; color:#326cc5; background:#eef5ff; }
    .ar-legend { display:flex; flex-wrap:wrap; gap:11px; margin-top:11px; color:#66758c; font-size:10px; }
    .ar-legend span { display:inline-flex; align-items:center; gap:5px; }
    .ar-legend i { width:9px; height:9px; border-radius:50%; background:#39bd71; }
    .ar-legend .is-wrong { background:#e34f70; }
    .ar-legend .is-blank { background:#a2adbd; }
    .ar-legend .is-review { background:#4e83df; }
    .ar-review-shell { min-width:0; }
    .ar-tabs { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); border-bottom:1px solid var(--line); }
    .ar-tabs a { display:flex; align-items:center; justify-content:center; gap:7px; min-height:49px; padding:8px 10px; border-bottom:2px solid transparent; color:#62728a; font-size:11px; font-weight:700; text-align:center; }
    .ar-tabs a:hover { color:var(--blue); background:#fbfdff; }
    .ar-tabs a.is-active { border-color:#4d82ff; color:var(--blue); }
    .ar-part-chips { display:flex; gap:7px; overflow-x:auto; padding:12px 13px; border-bottom:1px solid var(--line); scrollbar-width:thin; }
    .ar-part-chip { flex:0 0 auto; padding:7px 11px; border-radius:8px; color:#52637d; background:#f3f6fa; font-size:10px; white-space:nowrap; }
    .ar-part-chip.is-active { color:var(--blue); background:#eaf2ff; font-weight:700; }
    .ar-question-card { border-bottom:1px solid var(--line); scroll-margin-top:84px; }
    .ar-question-card:last-child { border-bottom:0; }
    .ar-question-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:13px 15px 0; }
    .ar-question-label { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
    .ar-question-label strong { font-size:13px; }
    .ar-status { display:inline-flex; align-items:center; gap:5px; padding:4px 8px; border-radius:7px; color:#218955; background:#e5f8ed; font-size:10px; font-weight:700; }
    .ar-status--wrong { color:#c84258; background:#fff0f2; }
    .ar-status--review { color:#326cc5; background:#eef5ff; }
    .ar-part-badge { flex:0 0 auto; padding:5px 9px; border-radius:999px; color:#2765cf; background:#eff5ff; font-size:10px; }
    .ar-question-body { display:grid; grid-template-columns:minmax(190px,.85fr) minmax(250px,1.2fr) minmax(205px,1fr); align-items:start; gap:13px; padding:11px 15px 15px; }
    .ar-question-media { width:100%; overflow:hidden; border:1px solid #e7ebf0; border-radius:8px; background:#eceff2; }
    .ar-question-media svg { display:block; width:100%; height:auto; }
    .ar-question-prompt { margin:0 0 9px; color:var(--ink); font-size:13px; font-weight:800; }
    .ar-options { display:grid; gap:5px; }
    .ar-option { display:grid; grid-template-columns:17px 1fr 17px; align-items:center; gap:7px; min-height:34px; padding:6px 9px; border:1px solid #e2e8f1; border-radius:7px; color:#465975; background:#fff; font-size:10px; }
    .ar-option-mark { width:14px; height:14px; display:grid; place-items:center; border:1.5px solid #9ba9bb; border-radius:50%; color:white; font-size:9px; }
    .ar-option--correct { border-color:#a6e4bb; color:#294d39; background:#effaf2; }
    .ar-option--correct .ar-option-mark { border-color:#35bd70; background:#35bd70; }
    .ar-option--wrong { border-color:#ffc6ce; color:#6a4048; background:#fff3f4; }
    .ar-option--wrong .ar-option-mark { border-color:#e34f70; background:#e34f70; }
    .ar-option-check { color:#32b66d; text-align:right; }
    .ar-insights { display:grid; gap:7px; }
    .ar-insights--full { grid-column:1/-1; }
    .ar-insight { padding:9px 10px; border-radius:8px; color:#52647d; background:#f0faf3; font-size:10px; }
    .ar-insight h4 { margin:0 0 3px; color:#277c4d; font-size:10px; }
    .ar-insight p { margin:0; color:#52647d; font-size:10px; line-height:1.5; }
    .ar-review-copy { color:#52647d; font-size:10px; line-height:1.55; }
    .ar-review-copy > :last-child { margin-bottom:0; }
    .ar-insight--vocab { background:#eff5ff; }
    .ar-insight--vocab h4 { color:var(--blue); }
    .ar-vocab-list { margin:3px 0 0; padding-left:16px; color:#4d6385; font-size:10px; }
    .ar-insight--neutral { background:#f4f7fb; }
    .ar-insight--neutral h4 { color:#5b6f8e; }
    .ar-subsection { margin-top:14px; padding:15px; scroll-margin-top:84px; }
    .ar-subsection > .ar-section-title { margin-bottom:12px; }
    .ar-skill-list { display:grid; gap:11px; }
    .ar-skill-row { display:grid; grid-template-columns:minmax(120px,1fr) minmax(100px,2fr) auto; align-items:center; gap:12px; font-size:11px; }
    .ar-skill-row > span:first-child { color:#536783; }
    .ar-skill-row strong { color:var(--ink); font-size:11px; }
    .ar-skill-row .ar-progress { height:7px; }
    .ar-empty { padding:16px; border:1px dashed #d6e1ee; border-radius:9px; color:#70819b; background:#fafcff; font-size:11px; }
    .ar-empty p { margin:3px 0 0; font-size:10px; }
    .ar-feedback { padding:15px; }
    .ar-feedback + .ar-feedback { margin-top:8px; }
    .ar-feedback h4 { margin:0 0 5px; font-size:11px; }
    .ar-feedback p { margin:0; color:#5a6c84; font-size:10px; }
    .ar-feedback-criteria { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
    .ar-feedback-criteria span { padding:4px 7px; border-radius:6px; color:#526684; background:#f0f4f9; font-size:9px; }
    .ar-pending-note { margin-bottom:12px; padding:10px 12px; border:1px solid #f2dfb9; border-radius:9px; color:#805e27; background:#fff9ed; font-size:11px; }
    .ar-bottom-action { display:flex; justify-content:center; margin-top:12px; }

    @media (max-width: 1120px) {
        .ar-layout { grid-template-columns:minmax(0,1fr) 285px; gap:12px; }
        .ar-summary { grid-template-columns:132px minmax(0,1fr) 215px; gap:13px; }
        .ar-ring { width:126px; }
        .ar-question-body { grid-template-columns:minmax(170px,.8fr) minmax(220px,1.2fr); }
        .ar-insights { grid-column:1/-1; grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width: 900px) {
        .ar-layout { grid-template-columns:minmax(0,1fr); }
        .ar-side-column { position:static; grid-template-columns:repeat(2,minmax(0,1fr)); }
        .ar-side-card:first-child { grid-column:1/-1; }
    }
    @media (max-width: 640px) {
        .ar-heading { flex-direction:column; gap:10px; }
        .ar-heading h1 { font-size:22px; }
        .ar-actions { width:100%; }
        .ar-actions .button { flex:1; padding:0 8px; font-size:10px; }
        .ar-summary { grid-template-columns:minmax(0,1fr); text-align:center; }
        .ar-score-copy { max-width:440px; margin:auto; }
        .ar-metrics { grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 10px; padding:10px 0 0; border-top:1px solid var(--line); border-left:0; text-align:left; }
        .ar-metric { grid-template-columns:22px minmax(0,1fr); padding:4px 0; }
        .ar-metric strong { grid-column:2; text-align:left; }
        .ar-side-column { grid-template-columns:minmax(0,1fr); }
        .ar-side-card:first-child { grid-column:auto; }
        .ar-tabs a { min-height:45px; gap:4px; padding:7px 4px; font-size:9px; }
        .ar-tabs .app-icon { display:none; }
        .ar-question-head { align-items:flex-start; padding:11px 11px 0; }
        .ar-question-body { grid-template-columns:minmax(0,1fr); padding:10px 11px 13px; }
        .ar-question-media { max-width:460px; }
        .ar-insights { grid-column:auto; grid-template-columns:minmax(0,1fr); }
        .ar-skill-row { grid-template-columns:minmax(90px,1fr) minmax(90px,1.5fr) auto; gap:8px; font-size:10px; }
        .ar-side-card, .ar-subsection { padding:12px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .attempt-results { scroll-behavior:auto; }
        .attempt-results *, .attempt-results *::before, .attempt-results *::after { transition:none !important; animation:none !important; }
    }
</style>
