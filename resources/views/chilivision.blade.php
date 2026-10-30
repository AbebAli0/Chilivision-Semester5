<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <script>
        try {
            document.querySelector('meta[name="color-scheme"]').content = localStorage.getItem("color-scheme") ?? "light dark";
        } catch (e) {}
    </script>
    <title>Chili Vision · Deteksi &amp; Klasifikasi Penyakit Pada Daun Cabai</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/chilivision-logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        @property --bg { syntax: '<color>'; inherits: true; initial-value: #f4f8f4; }
        @property --bg-grad-start { syntax: '<color>'; inherits: true; initial-value: #f7fbf6; }
        @property --bg-grad-mid { syntax: '<color>'; inherits: true; initial-value: #edf6ed; }
        @property --bg-grad-end { syntax: '<color>'; inherits: true; initial-value: #dfefe2; }
        @property --surface { syntax: '<color>'; inherits: true; initial-value: #ffffff; }
        @property --surface-alt { syntax: '<color>'; inherits: true; initial-value: #f8fbf8; }
        @property --topbar-bg { syntax: '<color>'; inherits: true; initial-value: rgba(255, 255, 255, 0.88); }
        @property --text-main { syntax: '<color>'; inherits: true; initial-value: #17211b; }
        @property --dark { syntax: '<color>'; inherits: true; initial-value: #102a1d; }
        @property --text-muted { syntax: '<color>'; inherits: true; initial-value: #5b7060; }
        @property --green { syntax: '<color>'; inherits: true; initial-value: #177245; }
        @property --mint { syntax: '<color>'; inherits: true; initial-value: #e7f4e9; }
        @property --line { syntax: '<color>'; inherits: true; initial-value: #d8e6d9; }
        @property --code-bg { syntax: '<color>'; inherits: true; initial-value: #eaf3ea; }
        @property --code-text { syntax: '<color>'; inherits: true; initial-value: #14532d; }

        :root {
            font-family: 'Plus Jakarta Sans', Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --theme-ease: cubic-bezier(0.4, 0, 0.2, 1);
            --theme-duration: 0.55s;

            --bg-light: #f4f8f4;
            --bg-dark: #0a140e;
            --bg-grad-start-light: #f7fbf6;
            --bg-grad-mid-light: #edf6ed;
            --bg-grad-end-light: #dfefe2;
            --bg-grad-start-dark: #09130d;
            --bg-grad-mid-dark: #0d1c14;
            --bg-grad-end-dark: #11241a;

            --surface-light: #ffffff;
            --surface-dark: #122118;
            --surface-alt-light: #f8fbf8;
            --surface-alt-dark: #172a1f;
            --topbar-bg-light: rgba(255, 255, 255, 0.88);
            --topbar-bg-dark: rgba(18, 33, 24, 0.88);

            --text-main-light: #17211b;
            --text-main-dark: #e7f3eb;
            --dark-light: #102a1d;
            --dark-dark: #f0fdf4;
            --text-muted-light: #5b7060;
            --text-muted-dark: #9cb5a3;

            --green-light: #177245;
            --green-dark: #4ade80;
            --green-hover-light: #0f5a35;
            --green-hover-dark: #22c55e;
            --mint-light: #e7f4e9;
            --mint-dark: rgba(34, 197, 94, 0.16);
            --line-light: #d8e6d9;
            --line-dark: #243b2c;

            --blue-light: #0284c7;
            --blue-dark: #38bdf8;
            --blue-hover-light: #0369a1;
            --blue-hover-dark: #7dd3fc;
            --blue-bg-light: #e0f2fe;
            --blue-bg-dark: rgba(14, 165, 233, 0.16);

            --code-bg-light: #eaf3ea;
            --code-bg-dark: #1c3325;
            --code-text-light: #14532d;
            --code-text-dark: #86efac;

            --color-scheme: light;
            --bg: var(--bg-light);
            --bg-grad-start: var(--bg-grad-start-light);
            --bg-grad-mid: var(--bg-grad-mid-light);
            --bg-grad-end: var(--bg-grad-end-light);
            --surface: var(--surface-light);
            --surface-alt: var(--surface-alt-light);
            --topbar-bg: var(--topbar-bg-light);
            --text-main: var(--text-main-light);
            --dark: var(--dark-light);
            --text-muted: var(--text-muted-light);
            --green: var(--green-light);
            --green-hover: var(--green-hover-light);
            --mint: var(--mint-light);
            --line: var(--line-light);
            --blue: var(--blue-light);
            --blue-hover: var(--blue-hover-light);
            --blue-bg: var(--blue-bg-light);
            --code-bg: var(--code-bg-light);
            --code-text: var(--code-text-light);

            @media (prefers-color-scheme: dark) {
                --color-scheme: dark;
                --bg: var(--bg-dark);
                --bg-grad-start: var(--bg-grad-start-dark);
                --bg-grad-mid: var(--bg-grad-mid-dark);
                --bg-grad-end: var(--bg-grad-end-dark);
                --surface: var(--surface-dark);
                --surface-alt: var(--surface-alt-dark);
                --topbar-bg: var(--topbar-bg-dark);
                --text-main: var(--text-main-dark);
                --dark: var(--dark-dark);
                --text-muted: var(--text-muted-dark);
                --green: var(--green-dark);
                --green-hover: var(--green-hover-dark);
                --mint: var(--mint-dark);
                --line: var(--line-dark);
                --blue: var(--blue-dark);
                --blue-hover: var(--blue-hover-dark);
                --blue-bg: var(--blue-bg-dark);
                --code-bg: var(--code-bg-dark);
                --code-text: var(--code-text-dark);
            }

            &:has(> head > meta[name="color-scheme"][content="dark"]) {
                color-scheme: dark;
                --color-scheme: dark;
                --bg: var(--bg-dark);
                --bg-grad-start: var(--bg-grad-start-dark);
                --bg-grad-mid: var(--bg-grad-mid-dark);
                --bg-grad-end: var(--bg-grad-end-dark);
                --surface: var(--surface-dark);
                --surface-alt: var(--surface-alt-dark);
                --topbar-bg: var(--topbar-bg-dark);
                --text-main: var(--text-main-dark);
                --dark: var(--dark-dark);
                --text-muted: var(--text-muted-dark);
                --green: var(--green-dark);
                --green-hover: var(--green-hover-dark);
                --mint: var(--mint-dark);
                --line: var(--line-dark);
                --blue: var(--blue-dark);
                --blue-hover: var(--blue-hover-dark);
                --blue-bg: var(--blue-bg-dark);
                --code-bg: var(--code-bg-dark);
                --code-text: var(--code-text-dark);
            }

            &:has(> head > meta[name="color-scheme"][content="light"]) {
                color-scheme: light;
                --color-scheme: light;
                --bg: var(--bg-light);
                --bg-grad-start: var(--bg-grad-start-light);
                --bg-grad-mid: var(--bg-grad-mid-light);
                --bg-grad-end: var(--bg-grad-end-light);
                --surface: var(--surface-light);
                --surface-alt: var(--surface-alt-light);
                --topbar-bg: var(--topbar-bg-light);
                --text-main: var(--text-main-light);
                --dark: var(--dark-light);
                --text-muted: var(--text-muted-light);
                --green: var(--green-light);
                --green-hover: var(--green-hover-light);
                --mint: var(--mint-light);
                --line: var(--line-light);
                --blue: var(--blue-light);
                --blue-hover: var(--blue-hover-light);
                --blue-bg: var(--blue-bg-light);
                --code-bg: var(--code-bg-light);
                --code-text: var(--code-text-light);
            }

            color-scheme: light dark;
            accent-color: var(--green);
            color: var(--text-main);
            background: var(--bg);
            --amber: #b45309;
            --amber-bg: #fef3c7;
            --purple: #7c3aed;
            --purple-hover: #6d28d9;
            --purple-bg: #ede9fe;
        }
        * { box-sizing: border-box; }
        ::view-transition-group(root) {
            animation-duration: 0.42s;
            animation-timing-function: cubic-bezier(0.22, 1, 0.36, 1);
        }
        ::view-transition-old(root) {
            animation: themeSmoothFadeOut 0.42s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        ::view-transition-new(root) {
            animation: themeSmoothFadeIn 0.42s cubic-bezier(0.22, 1, 0.36, 1) both;
            mix-blend-mode: normal;
        }
        @keyframes themeSmoothFadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        @keyframes themeSmoothFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        :root.theme-fallback-transition,
        :root.theme-fallback-transition body,
        :root.theme-fallback-transition .topbar,
        :root.theme-fallback-transition .kpi-card,
        :root.theme-fallback-transition .card,
        :root.theme-fallback-transition .chat-box,
        :root.theme-fallback-transition .chat-bubble,
        :root.theme-fallback-transition .chat-quick-chips,
        :root.theme-fallback-transition .chip,
        :root.theme-fallback-transition .chat-input-row,
        :root.theme-fallback-transition .spray-box,
        :root.theme-fallback-transition select,
        :root.theme-fallback-transition input,
        :root.theme-fallback-transition .threshold-box,
        :root.theme-fallback-transition .threshold-badge,
        :root.theme-fallback-transition button.threshold-reset-btn,
        :root.theme-fallback-transition .upload-zone,
        :root.theme-fallback-transition .result,
        :root.theme-fallback-transition .db-banner,
        :root.theme-fallback-transition table.api-table th,
        :root.theme-fallback-transition table.api-table td,
        :root.theme-fallback-transition code,
        :root.theme-fallback-transition .badge {
            transition:
                --bg 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                --bg-grad-start 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                --bg-grad-mid 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                --bg-grad-end 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                --surface 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                --surface-alt 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                --topbar-bg 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                --text-main 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                --dark 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                --text-muted 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                --line 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                background-color 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                color 0.42s cubic-bezier(0.22, 1, 0.36, 1),
                border-color 0.42s cubic-bezier(0.22, 1, 0.36, 1);
        }
        body {
            margin: 0;
            background: linear-gradient(135deg, var(--bg-grad-start) 0%, var(--bg-grad-mid) 55%, var(--bg-grad-end) 100%);
            color: var(--text-main);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        .shell {
            max-width: 1360px;
            margin: auto;
            padding: 24px 22px 60px;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            background: var(--topbar-bg);
            backdrop-filter: blur(12px);
            padding: 14px 22px;
            border-radius: 18px;
            border: 1px solid var(--line);
            box-shadow: 0 10px 28px rgba(16, 42, 29, 0.05);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
            font-weight: 800;
            letter-spacing: -.025em;
            color: var(--dark);
            font-size: 1.15rem;
        }
        .brand-mark {
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #1d8a53 0%, #125c37 100%);
            color: #eaffeb;
            font-size: 20px;
            box-shadow: 0 6px 14px rgba(23, 114, 69, 0.25);
        }
        .top-badges {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: .78rem;
            font-weight: 700;
            padding: 7px 13px;
            border-radius: 99px;
            background: var(--mint);
            color: var(--green);
            text-decoration: none;
            border: 1px solid rgba(23, 114, 69, 0.12);
            transition: transform .15s, box-shadow .15s;
        }
        a.badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(16, 42, 29, 0.08);
        }
        .badge.mysql { background: var(--blue-bg); color: var(--blue-hover); border-color: rgba(3, 105, 161, 0.15); }
        .badge.laravel { background: rgba(239, 68, 68, 0.14); color: var(--laravel-text, #dc2626); border-color: rgba(185, 28, 28, 0.2); }
        button.theme-toggle-btn {
            width: auto;
            padding: 7px 14px;
            border-radius: 99px;
            font-size: .78rem;
            font-weight: 700;
            background: var(--surface-alt);
            color: var(--dark);
            border: 1px solid var(--line);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
            user-select: none;
            transition: transform 0.1s ease, box-shadow 0.1s ease, background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }
        button.theme-toggle-btn:hover:not(:disabled) {
            background: var(--mint);
            color: var(--green);
            border-color: var(--green);
            transform: none;
        }
        button.theme-toggle-btn:active:not(:disabled),
        button.theme-toggle-btn.is-pressed:not(:disabled) {
            transform: scale(0.94) translateY(1px);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.14);
        }
        .if-dark { display: none; }
        @media (prefers-color-scheme: dark) {
            :root:not(:has(> head > meta[name="color-scheme"][content="light"])) .if-light { display: none !important; }
            :root:not(:has(> head > meta[name="color-scheme"][content="light"])) .if-dark { display: inline-flex !important; }
        }
        :root:has(> head > meta[name="color-scheme"][content="dark"]) .if-light { display: none !important; }
        :root:has(> head > meta[name="color-scheme"][content="dark"]) .if-dark { display: inline-flex !important; }
        @container not style(--color-scheme: dark) {
            .if-dark { display: none !important; }
        }
        @container not style(--color-scheme: light) {
            .if-light { display: none !important; }
        }

        .hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 24px;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--green);
            font-size: .76rem;
            font-weight: 800;
            letter-spacing: .11em;
            text-transform: uppercase;
        }
        .eyebrow:before {
            content: "";
            width: 22px;
            height: 2.5px;
            border-radius: 99px;
            background: var(--green);
        }
        .hero h1 {
            font-size: clamp(1.75rem, 3vw, 2.55rem);
            line-height: 1.12;
            letter-spacing: -.035em;
            margin: 8px 0 4px;
            color: var(--dark);
            font-weight: 800;
        }

        /* KPI Top Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }
        .kpi-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 18px 20px;
            box-shadow: 0 10px 26px rgba(16, 42, 29, 0.045);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(16, 42, 29, 0.075);
        }
        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: .77rem;
            font-weight: 800;
            color: var(--text-muted);
            margin-bottom: 10px;
            letter-spacing: .01em;
        }
        .kpi-badge {
            padding: 3px 9px;
            border-radius: 99px;
            background: var(--mint);
            color: var(--green);
            font-size: .72rem;
            font-weight: 700;
        }
        .kpi-val {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 5px;
            letter-spacing: -.02em;
            font-variant-numeric: tabular-nums;
        }
        .kpi-sub {
            font-size: .78rem;
            color: var(--text-muted);
            line-height: 1.45;
        }
        .pipeline-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: .69rem;
            font-weight: 700;
            padding: 4px 9px;
            border-radius: 7px;
            background: var(--blue-bg);
            color: var(--blue-hover);
            border: 1px solid rgba(56, 189, 248, 0.25);
            margin-top: 10px;
        }

        /* Main Grid */
        .workspace {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(360px, .85fr);
            gap: 20px;
            margin-bottom: 22px;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 22px;
            box-shadow: 0 14px 36px rgba(16, 42, 29, 0.055);
        }
        .card-title {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }
        .card h2 {
            color: var(--dark);
            font-size: 1.12rem;
            font-weight: 800;
            letter-spacing: -.02em;
            margin: 0;
        }
        .card-subtitle {
            color: var(--text-muted);
            font-size: .81rem;
            margin: 4px 0 0;
            line-height: 1.4;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--green);
            background: var(--mint);
            border: 1px solid var(--line);
            border-radius: 99px;
            padding: 6px 12px;
            font-size: .74rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .pill:before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2);
        }

        /* Camera Preview */
        .preview {
            position: relative;
            background: radial-gradient(circle at center, #183828 0%, #0d2117 100%);
            border-radius: 16px;
            width: 100%;
            min-height: 360px;
            height: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 3px solid var(--line);
            box-shadow: inset 0 2px 10px rgba(0, 0, 0, 0.25);
            margin-left: auto;
            margin-right: auto;
        }
        .preview:has(#uploadedImage:not([hidden])),
        .preview:has(#video:not([hidden])) {
            width: fit-content;
            max-width: 100%;
            min-height: 0;
            height: auto;
        }
        #video { display: block; width: auto; height: auto; max-width: 100%; object-fit: contain; }
        #uploadedImage { display: block; width: auto; height: auto; max-width: 100%; object-fit: contain; }
        [hidden] { display: none !important; }
        .overlay { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; }
        .placeholder { color: #b9d1bd; padding: 40px; text-align: center; line-height: 1.6; max-width: 440px; }
        .placeholder-icon {
            font-size: 2.2rem;
            margin-bottom: 10px;
            display: inline-grid;
            place-items: center;
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .placeholder strong { display: block; color: #ecf8ed; font-size: 1.05rem; margin-bottom: 6px; }

        .toolbar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-top: 14px;
        }
        .field-label {
            display: block;
            font-weight: 700;
            color: var(--text-main);
            font-size: .79rem;
            margin-bottom: 6px;
        }
        button, select, input[type="number"], input[type="text"] {
            width: 100%;
            border-radius: 11px;
            padding: 10px 14px;
            font-size: .87rem;
            font-family: inherit;
        }
        button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 0;
            background: linear-gradient(180deg, #1b804e 0%, #15683f 100%);
            color: #ffffff;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(23, 114, 69, 0.2);
            transition: transform .14s ease, box-shadow .14s ease, filter .14s ease, background .14s ease;
        }
        button:hover:not(:disabled) {
            background: linear-gradient(180deg, #156e42 0%, #0f5633 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(23, 114, 69, 0.28);
        }
        button:active:not(:disabled) {
            transform: translateY(0);
        }
        button:disabled { opacity: .55; cursor: not-allowed; box-shadow: none; }

        button.secondary {
            background: var(--surface-alt);
            color: var(--green);
            border: 1px solid var(--line);
            box-shadow: 0 2px 6px rgba(16, 42, 29, 0.04);
        }
        button.secondary:hover:not(:disabled) {
            background: var(--mint);
            box-shadow: 0 4px 10px rgba(16, 42, 29, 0.08);
        }

        button.water-btn {
            background: linear-gradient(180deg, #0ea5e9 0%, #0284c7 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.24);
        }
        button.water-btn:hover:not(:disabled) {
            background: linear-gradient(180deg, #0284c7 0%, #0369a1 100%);
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.32);
        }

        button.pesticide-btn {
            background: linear-gradient(180deg, #8b5cf6 0%, #7c3aed 100%);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.24);
        }
        button.pesticide-btn:hover:not(:disabled) {
            background: linear-gradient(180deg, #7c3aed 0%, #6d28d9 100%);
            box-shadow: 0 6px 16px rgba(124, 58, 237, 0.32);
        }

        select, input[type="number"], input[type="text"] {
            background: var(--surface-alt);
            color: var(--text-main);
            border: 1px solid var(--line);
            outline: none;
            transition: border-color .15s, box-shadow .15s, background-color .15s;
        }
        select:focus, input[type="number"]:focus, input[type="text"]:focus {
            background: var(--surface);
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(23, 114, 69, 0.14);
        }

        .threshold-box {
            margin-top: 14px;
            padding: 12px 15px;
            border-radius: 14px;
            background: var(--surface-alt);
            border: 1px solid var(--line);
        }
        .threshold-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 6px;
        }
        .threshold-actions {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .threshold-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 99px;
            background: var(--mint);
            color: var(--green);
            font-size: .78rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            border: 1px solid var(--line);
        }
        button.threshold-reset-btn {
            width: auto;
            padding: 4px 10px;
            border-radius: 99px;
            font-size: .72rem;
            font-weight: 700;
            background: var(--surface);
            color: var(--text-muted);
            border: 1px solid var(--line);
            box-shadow: none;
        }
        button.threshold-reset-btn:hover:not(:disabled) {
            background: var(--mint);
            color: var(--green);
            border-color: var(--green);
            transform: none;
            box-shadow: none;
        }
        input[type="range"].threshold-slider {
            --slider-track-size: 0.45rem;
            --slider-thumb-size: 1.15rem;
            --slider-track-radius: 999px;
            --slider-thumb-radius: 50%;
            --slider-track-color: var(--line);
            --slider-fill-color: var(--green);
            --slider-thumb-color: var(--slider-fill-color);
            --slider-direction: to right;
            --attr-min: attr(min type(<number>), 0.1);
            --attr-max: attr(max type(<number>), 0.95);
            --control-value: attr(value type(<number>), 0.45);
            --slider-progress: calc(
                progress(var(--control-value), var(--attr-min), var(--attr-max)) * 100%
            );

            appearance: none;
            inline-size: 100%;
            block-size: 1.9rem;
            margin: 0;
            padding: 0;
            border: 0;
            border-radius: 0;
            background: transparent;
            cursor: pointer;
            accent-color: var(--green);
        }
        input[type="range"].threshold-slider:dir(rtl) {
            --slider-direction: to left;
        }
        @supports not (opacity: progress(1, 0, 100)) {
            input[type="range"].threshold-slider {
                --slider-progress: calc(
                    (var(--control-value) - var(--attr-min)) /
                    (var(--attr-max) - var(--attr-min)) * 100%
                );
            }
        }
        input[type="range"].threshold-slider::-webkit-slider-runnable-track {
            block-size: var(--slider-track-size);
            border: 0;
            border-radius: var(--slider-track-radius);
            background: linear-gradient(
                var(--slider-direction),
                var(--slider-fill-color) var(--slider-progress),
                var(--slider-track-color) var(--slider-progress)
            );
        }
        input[type="range"].threshold-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            inline-size: var(--slider-thumb-size);
            block-size: var(--slider-thumb-size);
            margin-block-start: calc(
                (var(--slider-track-size) - var(--slider-thumb-size)) / 2
            );
            border: 2px solid var(--surface);
            border-radius: var(--slider-thumb-radius);
            background: var(--slider-thumb-color);
            box-shadow: 0 2px 6px rgba(16, 42, 29, 0.22);
        }
        input[type="range"].threshold-slider::-moz-range-track {
            block-size: var(--slider-track-size);
            border: 0;
            border-radius: var(--slider-track-radius);
            background: var(--slider-track-color);
        }
        input[type="range"].threshold-slider::-moz-range-progress {
            block-size: var(--slider-track-size);
            border-radius: var(--slider-track-radius);
            background: var(--slider-fill-color);
        }
        input[type="range"].threshold-slider::-moz-range-thumb {
            inline-size: var(--slider-thumb-size);
            block-size: var(--slider-thumb-size);
            border: 2px solid var(--surface);
            border-radius: var(--slider-thumb-radius);
            background: var(--slider-thumb-color);
            box-shadow: 0 2px 6px rgba(16, 42, 29, 0.22);
        }
        input[type="range"].threshold-slider:focus-visible {
            outline: 2px solid var(--slider-fill-color);
            outline-offset: 4px;
        }
        .threshold-hint {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            font-size: .74rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .upload-zone {
            border: 1.5px dashed var(--line);
            border-radius: 15px;
            padding: 15px;
            margin-top: 14px;
            background: var(--surface-alt);
        }
        .upload-zone input[type="file"] { width: 100%; margin-top: 4px; font-size: .82rem; color: var(--text-muted); }
        .status { color: var(--green); min-height: 20px; margin: 12px 0 0; font-size: .84rem; font-weight: 600; }
        .db-banner {
            margin-top: 12px;
            padding: 11px 14px;
            border-radius: 12px;
            font-size: .82rem;
            font-weight: 600;
            background: var(--blue-bg);
            color: var(--blue-hover);
            border: 1px solid rgba(56, 189, 248, 0.3);
        }
        .db-banner.saved { background: var(--mint); color: var(--green); border-color: rgba(34, 197, 94, 0.35); }
        .db-banner.skipped { background: rgba(245, 158, 11, 0.15); color: #d97706; border-color: rgba(245, 158, 11, 0.35); }

        .result {
            color: var(--text-main);
            background: var(--surface-alt);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 14px;
            margin-top: 12px;
            font-size: .86rem;
        }
        .detection {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid var(--line);
        }
        .detection:last-child { border-bottom: 0; }
        .confidence { color: var(--green); font-weight: 800; font-variant-numeric: tabular-nums; }

        /* Chatbot Panel */
        .chat-box {
            display: flex;
            flex-direction: column;
            height: 435px;
            border: 1px solid var(--line);
            border-radius: 16px;
            background: var(--surface-alt);
            overflow: hidden;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .chat-bubble {
            max-width: 88%;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: .83rem;
            line-height: 1.5;
            white-space: pre-line;
            box-shadow: 0 2px 6px rgba(16, 42, 29, 0.04);
        }
        .chat-bubble.bot {
            align-self: flex-start;
            background: var(--surface);
            border: 1px solid var(--line);
            color: var(--text-main);
            border-bottom-left-radius: 4px;
        }
        .chat-bubble.user {
            align-self: flex-end;
            background: linear-gradient(135deg, #1b804e 0%, #14633b 100%);
            color: #ffffff;
            border-bottom-right-radius: 4px;
        }
        .chat-time {
            display: block;
            font-size: .68rem;
            opacity: .72;
            margin-top: 4px;
        }
        .chat-inline-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .chat-inline-btn {
            width: auto;
            padding: 7px 12px;
            font-size: .76rem;
            border-radius: 9px;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(16, 42, 29, 0.08);
        }
        .chat-inline-btn.confirm-yes {
            background: linear-gradient(180deg, #1b804e 0%, #15683f 100%);
            color: #ffffff;
        }
        .chat-inline-btn.confirm-no {
            background: rgba(239, 68, 68, 0.16);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .chat-inline-btn.confirm-no:hover:not(:disabled) {
            background: rgba(239, 68, 68, 0.25);
        }
        .chat-inline-btn.model-option {
            background: var(--mint);
            color: var(--green);
            border: 1px solid rgba(23, 114, 69, 0.25);
        }
        .chat-inline-btn.model-option:hover:not(:disabled) {
            background: #d7eedc;
        }
        .chat-inline-btn.model-option.active {
            background: linear-gradient(180deg, #1b804e 0%, #15683f 100%);
            color: #ffffff;
            border-color: transparent;
        }
        .chat-photo-preview {
            display: block;
            width: 100%;
            max-width: 240px;
            max-height: 220px;
            border-radius: 10px;
            margin-top: 8px;
            object-fit: cover;
            border: 1px solid rgba(16, 42, 29, 0.16);
            cursor: zoom-in;
        }
        .chat-quick-chips {
            display: flex;
            gap: 7px;
            padding: 9px 12px;
            background: var(--surface-alt);
            overflow-x: auto;
            border-top: 1px solid var(--line);
        }
        .chip {
            width: auto;
            padding: 6px 11px;
            font-size: .74rem;
            border-radius: 99px;
            background: var(--surface);
            color: var(--dark);
            border: 1px solid var(--line);
            white-space: nowrap;
            font-weight: 700;
            box-shadow: 0 1px 3px rgba(16, 42, 29, 0.05);
        }
        .chip:hover:not(:disabled) {
            background: var(--mint);
            color: var(--green);
            border-color: var(--green);
        }
        .chat-input-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            padding: 10px;
            background: var(--surface);
            border-top: 1px solid var(--line);
        }

        /* Bottom 3-Column Section: Sensors & Manual Spray */
        .iot-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 22px;
        }
        .spray-box {
            background: var(--surface-alt);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            margin-bottom: 12px;
        }
        .spray-box h4 {
            margin: 0 0 6px;
            font-size: .88rem;
            color: var(--dark);
            font-weight: 800;
        }
        .spray-meta {
            font-size: .8rem;
            color: var(--text-muted);
            line-height: 1.55;
        }
        table.api-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .83rem;
        }
        table.api-table th, table.api-table td {
            text-align: left;
            padding: 11px 13px;
            border-bottom: 1px solid var(--line);
        }
        table.api-table th {
            background: var(--surface-alt);
            color: var(--dark);
            font-weight: 800;
        }
        table.api-table a {
            color: var(--green);
            font-weight: 700;
            text-decoration: none;
        }
        table.api-table a:hover {
            text-decoration: underline;
        }
        code {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            background: var(--code-bg);
            padding: 2px 6px;
            border-radius: 5px;
            font-size: .77rem;
            color: var(--code-text);
        }
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 22px;
        }
        .hardware-banner {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 20px;
            margin-bottom: 22px;
        }
        .esp32-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 800;
            border: 1px solid var(--line);
            background: var(--surface-alt);
            color: var(--text-main);
        }
        .esp32-pill.online { background: rgba(16, 185, 129, .14); color: #059669; border-color: rgba(16, 185, 129, .38); }
        .esp32-pill.warning { background: rgba(245, 158, 11, .14); color: #d97706; border-color: rgba(245, 158, 11, .38); }
        .esp32-pill.offline { background: rgba(239, 68, 68, .14); color: #dc2626; border-color: rgba(239, 68, 68, .38); }
        .esp32-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            display: inline-block;
        }
        .chart-frame {
            width: 100%;
            background: var(--surface-alt);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px;
            margin-top: 8px;
        }
        .chart-plot-wrap {
            position: relative;
            width: 100%;
            border-radius: 10px;
            outline: none;
            touch-action: pan-y;
            cursor: crosshair;
        }
        .chart-plot-wrap:focus-visible {
            box-shadow: 0 0 0 2px var(--green);
        }
        .chart-svg {
            width: 100%;
            height: 195px;
            display: block;
            overflow: visible;
            user-select: none;
        }
        .chart-dot {
            transition: r 0.14s ease, stroke-width 0.14s ease, opacity 0.14s ease;
        }
        .chart-tooltip {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 25;
            min-width: 168px;
            max-width: 235px;
            padding: 9px 11px;
            border-radius: 11px;
            background: var(--surface);
            border: 1px solid var(--line);
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.18);
            color: var(--text-main);
            font-size: .74rem;
            line-height: 1.45;
            pointer-events: auto;
            transform: translate3d(0, 0, 0);
        }
        .chart-tooltip[hidden] {
            display: none !important;
        }
        .chart-tooltip-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-size: .7rem;
            font-weight: 700;
            color: var(--text-muted);
            padding-bottom: 5px;
            margin-bottom: 5px;
            border-bottom: 1px solid var(--line);
        }
        .chart-tooltip-pin {
            font-size: .66rem;
            font-weight: 800;
            padding: 1px 6px;
            border-radius: 999px;
            background: var(--mint);
            color: var(--green);
        }
        .chart-tooltip-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 2px;
        }
        .chart-tooltip-meta {
            margin-top: 5px;
            padding-top: 4px;
            border-top: 1px dashed var(--line);
            font-size: .69rem;
            color: var(--text-muted);
        }
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: .75rem;
            color: var(--text-muted);
            margin-top: 8px;
            font-weight: 600;
        }
        .chart-inspector {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 8px;
            padding: 7px 10px;
            border-radius: 10px;
            background: var(--surface);
            border: 1px solid var(--line);
            font-size: .73rem;
            color: var(--text-main);
        }
        .chart-nav-group {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .chart-nav-btn {
            width: auto;
            padding: 3px 8px;
            border-radius: 7px;
            font-size: .72rem;
            font-weight: 700;
            background: var(--surface-alt);
            color: var(--text-main);
            border: 1px solid var(--line);
            cursor: pointer;
        }
        .chart-nav-btn:hover {
            background: var(--mint);
            color: var(--green);
            transform: none;
        }
        .stat-bar-row {
            margin-bottom: 10px;
        }
        .stat-bar-label {
            display: flex;
            justify-content: space-between;
            font-size: .78rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-main);
        }
        .stat-bar-track {
            width: 100%;
            height: 8px;
            border-radius: 999px;
            background: var(--line);
            overflow: hidden;
        }
        .stat-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--green), #34d399);
        }
        .export-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        .export-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 9px 12px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: var(--surface-alt);
            color: var(--text-main);
            font-size: .78rem;
            font-weight: 700;
            text-decoration: none;
            transition: transform .12s ease, border-color .15s ease;
        }
        .export-btn:hover {
            transform: translateY(-1px);
            border-color: var(--green);
            color: var(--green);
        }
        .footer {
            color: var(--text-muted);
            text-align: center;
            font-size: .8rem;
            margin-top: 32px;
            font-weight: 600;
        }
        @media (max-width: 1024px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .workspace { grid-template-columns: 1fr; }
            .iot-grid { grid-template-columns: 1fr; }
            .analytics-grid { grid-template-columns: 1fr; }
            .hardware-banner { grid-template-columns: 1fr; }
        }
        @media (max-width: 600px) {
            .kpi-grid { grid-template-columns: 1fr; }
            .toolbar { grid-template-columns: 1fr; }
            .export-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
