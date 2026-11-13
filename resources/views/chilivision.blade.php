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
<body>
<main class="shell">
    <nav class="topbar">
        <div class="brand">
            <span class="brand-mark" style="padding:0;overflow:hidden;"><img src="{{ asset('images/chilivision-logo.svg') }}" alt="Logo ChiliVision" style="width:100%;height:100%;object-fit:cover;display:block;"></span>
            <div>
                <div>Chili Vision · Deteksi &amp; Klasifikasi Penyakit Pada Daun Cabai</div>
            </div>
        </div>
        <div class="top-badges">
            <button type="button" id="themeToggleBtn" class="theme-toggle-btn" aria-label="Ubah Tema Terang atau Gelap">
                <span class="if-light">🌙 Dark Mode</span>
                <span class="if-dark">☀️ Light Mode</span>
            </button>
            <span class="badge laravel">⚡ Laravel Full-Stack</span>
            <span class="badge mysql">🗄️ TiDB Cloud Connected</span>
            <span class="esp32-pill" id="navEsp32Badge"><span class="esp32-dot"></span><span>ESP32: Memuat...</span></span>
            <a class="badge" href="api" target="_blank">📚 Dokumentasi &amp; Playground API (/api) ↗</a>
            <a class="badge" href="api?format=json" target="_blank">📄 Raw JSON Spec ↗</a>
            <a class="badge" href="api/dashboard/state" target="_blank">📡 Live State JSON ↗</a>
        </div>
    </nav>

    <section class="hero">
        <div>
            <span class="eyebrow">Computer Vision · IoT Sensor · Smart Chatbot</span>
            <h1>Monitoring Tanaman Cabai Terintegrasi Database TiDB Cloud.</h1>
        </div>
    </section>

    <!-- 4 Real-Time Summary Cards -->
    <section class="kpi-grid">
        <div class="kpi-card">
            <div>
                <div class="kpi-header">
                    <span>🍃 DETEKSI DAUN TERAKHIR</span>
                    <span class="kpi-badge" id="kpiLeafCount">0 tersimpan</span>
                </div>
                <div class="kpi-val" id="kpiLeafName">Memuat...</div>
                <div class="kpi-sub" id="kpiLeafMeta">Jika daun tidak berubah, gambar tidak dikirim ke database.</div>
            </div>
            <div><span class="pipeline-tag">🔗 API Gambar Daun → TiDB Cloud</span></div>
        </div>

        <div class="kpi-card">
            <div>
                <div class="kpi-header">
                    <span>🌱 SENSOR SOIL MOISTURE</span>
                    <span class="kpi-badge" id="kpiSoilStatus">Optimal</span>
                </div>
                <div class="kpi-val" id="kpiSoilVal">-- %</div>
                <div class="kpi-sub" id="kpiSoilMeta">Menunggu pembacaan sensor...</div>
            </div>
            <div><span class="pipeline-tag">🔄 Web &gt; Chatbot &gt; Database</span></div>
        </div>

        <div class="kpi-card">
            <div>
                <div class="kpi-header">
                    <span>🌡️ SENSOR DHT22 (SUHU &amp; UDARA)</span>
                    <span class="kpi-badge" id="kpiDhtStatus">Optimal</span>
                </div>
                <div class="kpi-val" id="kpiDhtVal">-- °C / -- %</div>
                <div class="kpi-sub" id="kpiDhtMeta">Menunggu pembacaan sensor...</div>
            </div>
            <div><span class="pipeline-tag">🔄 Web &gt; Chatbot &gt; Database</span></div>
        </div>

        <div class="kpi-card">
            <div>
                <div class="kpi-header">
                    <span>⏱️ HISTORI PENYEMPROTAN TERAKHIR</span>
                    <span class="kpi-badge">TiDB Cloud Log</span>
                </div>
                <div class="kpi-sub" style="margin-top:4px;">
                    💧 <strong>Air:</strong> <span id="kpiLastWater">Belum ada</span><br>
                    🧪 <strong>Pestisida:</strong> <span id="kpiLastPesticide">Belum ada</span>
                </div>
            </div>
            <div><span class="pipeline-tag">🎛️ Web Button &amp; Chatbot Command</span></div>
        </div>
    </section>

    <!-- Workspace: Left = Computer Vision, Right = Chatbot & Last Leaf DB Card -->
    <section class="workspace">
        <!-- Computer Vision Card -->
        <div class="card">
            <div class="card-title">
                <div>
                    <h2>📷 1. Computer Vision — API Gambar Daun</h2>
                    <p class="card-subtitle">Deteksi terakhir daun cabai. Jika daun tidak berubah, gambar tidak dikirim ulang ke database TiDB Cloud.</p>
                </div>
                <span class="pill" id="cvPill">CV Ready</span>
            </div>

            <div class="preview">
                <video id="video" autoplay playsinline hidden></video>
                <img id="uploadedImage" alt="Gambar upload dengan hasil deteksi" hidden>
                <canvas id="overlay" class="overlay" hidden></canvas>
                <div id="placeholder" class="placeholder">
                    <div class="placeholder-icon">🍃</div>
                    <strong>Belum ada gambar daun untuk dianalisis</strong>
                    Aktifkan kamera langsung atau upload foto daun cabai dari perangkat Anda.
                </div>
            </div>

            <div class="toolbar">
                <div>
                    <label class="field-label" for="cameraSelect">🎥 Sumber kamera</label>
                    <select id="cameraSelect"><option>Memuat kamera...</option></select>
                </div>
                <div>
                    <label class="field-label" for="model">🧠 Model analisis AI (ONNX)</label>
                    @php
                        $modelsDir = base_path('models');
                        $initialModels = [
                            [
                                'id' => 'yolov8n-chili',
                                'name' => 'Yolov8n (Deteksi Penyakit Daun Cabai)',
                                'available' => is_file($modelsDir . DIRECTORY_SEPARATOR . 'yolov8n_chili.onnx'),
                            ],
                            [
                                'id' => 'cnn-5ch-chili',
                                'name' => 'CNN (Klasifikasi Penyakit Daun Cabai)',
                                'available' => is_file($modelsDir . DIRECTORY_SEPARATOR . 'cnn_5ch_7kelas_hard_negative.onnx'),
                            ],
                        ];
                    @endphp
                    <select id="model">
                        @foreach($initialModels as $idx => $m)
                            <option value="{{ $m['id'] }}" {{ !$m['available'] ? 'disabled' : '' }} {{ $idx === 0 ? 'selected' : '' }}>
                                {{ $m['name'] }}{{ !$m['available'] ? ' — belum tersedia' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="threshold-box">
                <div class="threshold-header">
                    <label class="field-label" for="thresholdSlider" style="margin-bottom:0;">🎯 Batas Keyakinan (Confidence Threshold)</label>
                    <div class="threshold-actions">
                        <span class="threshold-badge" id="thresholdValueLabel">45%</span>
                        <button type="button" class="threshold-reset-btn" id="thresholdResetBtn" title="Kembalikan ke nilai rekomendasi model">↺ Default</button>
                    </div>
                </div>
                <input
                    type="range"
                    id="thresholdSlider"
                    class="threshold-slider"
                    min="0.10"
                    max="0.95"
                    step="0.05"
                    value="0.25"
                    aria-valuetext="25%"
                >
                <div class="threshold-hint">
                    <span>10% (Sensitif)</span>
                    <span id="thresholdModeHint">Rekomendasi YOLOv8n: 25% · CNN: 50%</span>
                    <span>95% (Ketat)</span>
                </div>
            </div>

            <div class="toolbar">
                <button id="camera">📷 Aktifkan kamera</button>
                <button id="predict" class="secondary" disabled>🔍 Mulai deteksi langsung</button>
            </div>

            <div class="upload-zone">
                <label class="field-label" for="upload">📁 Atau uji API Gambar Daun dengan upload foto</label>
                <input id="upload" type="file" accept="image/jpeg,image/png,image/webp">
                <div class="toolbar" style="margin-top:10px;">
                    <button id="uploadPredict" class="secondary">📤 Upload &amp; Analisis Daun</button>
                    <button id="showCamera" class="secondary" hidden>🎥 Kembali ke Kamera</button>
                </div>
            </div>

            <p class="status" id="status">✅ Model AI ONNX siap digunakan.</p>
            <div class="db-banner" id="dbStatusBanner">
                Status Database Daun: Menunggu deteksi gambar daun...
            </div>
            <div class="result" id="result">Hasil deteksi bounding box / klasifikasi penyakit akan tampil di sini.</div>
        </div>

        <!-- Right Column: Chatbot & Latest Leaf in DB -->
        <div style="display:flex;flex-direction:column;gap:20px;">
            <!-- Smart Chatbot Card -->
            <div class="card">
                <div class="card-title">
                    <div>
                        <h2>🤖 Smart Chatbot &amp; Command Center</h2>
                        <p class="card-subtitle">Menerima data sensor otomatis (<code>Web &gt; Chatbot &gt; Database</code>) &amp; perintah semprot manual.</p>
                    </div>
                    <span class="pill">Online</span>
                </div>

                <div class="chat-box">
                    <div class="chat-messages" id="chatMessages">
                        <div class="chat-bubble bot">Memuat pesan chatbot dari database TiDB Cloud...</div>
                    </div>
                    <div class="chat-quick-chips">
                        <button type="button" class="chip" onclick="sendQuickCommand('/status')">📊 Status</button>
                        <button type="button" class="chip" onclick="sendQuickCommand('model')">🧠 Model</button>
                        <button type="button" class="chip" onclick="sendQuickCommand('/semprot_air')">💧 Semprot Air (5s)</button>
                        <button type="button" class="chip" onclick="sendQuickCommand('/semprot_pestisida')">🧪 Semprot Pestisida (5s)</button>
                        <button type="button" class="chip" onclick="sendQuickCommand('/cek_daun')">🍃 Cek Daun</button>
                        <button type="button" class="chip" onclick="sendQuickCommand('/cek_tanah')">🌱 Cek Tanah</button>
                        <button type="button" class="chip" onclick="sendQuickCommand('/cek_suhu')">🌡️ Cek Suhu</button>
                        <button type="button" class="chip" onclick="sendQuickCommand('/histori')">⏱️ Histori</button>
                    </div>
                    <form id="chatForm" class="chat-input-row" onsubmit="handleChatSubmit(event)">
                        <input type="text" id="chatInput" placeholder="Ketik 'model', 'model yolov8n', 'semprot air', atau 'status'..." autocomplete="off">
                        <button type="submit" style="width:auto;padding:10px 18px;">📨 Kirim</button>
                    </form>
                </div>
            </div>

            <!-- Latest Saved Leaf in TiDB Cloud Card -->
            <div class="card">
                <div class="card-title">
                    <div>
                        <h2>🍃 Deteksi Daun Terakhir di TiDB Cloud</h2>
                        <p class="card-subtitle">Tabel <code>leaf_detections</code> (hanya tersimpan saat daun berubah)</p>
                    </div>
                </div>
                <div id="latestLeafDbBox" style="display:flex;gap:14px;align-items:center;">
                    <img id="latestLeafThumb" src="" alt="Thumb" hidden style="width:auto;height:auto;max-width:110px;max-height:110px;object-fit:contain;border-radius:12px;border:2px solid var(--line);">
                    <div style="font-size:.83rem;line-height:1.55;color:var(--text-main);" id="latestLeafInfo">
                        Belum ada gambar daun yang disimpan ke database.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Bottom 3 Cards: Manual Spray Controls + Soil Moisture API + DHT11 API -->
    <section class="iot-grid">
        <!-- Manual Spray & History Card -->
        <div class="card">
            <div class="card-title">
                <div>
                    <h2>💦 Penyemprotan Manual &amp; Histori</h2>
                    <p class="card-subtitle">Tombol Web &amp; Histori Terakhir Air / Pestisida</p>
                </div>
            </div>

            <div style="margin-bottom:12px;">
                <label class="field-label" for="sprayDuration">⏱️ Durasi Penyemprotan Manual (detik)</label>
                <input type="number" id="sprayDuration" value="5" min="1" max="60">
            </div>

            <div class="toolbar" style="margin-bottom:14px;">
                <button type="button" class="water-btn" id="btnManualWater" onclick="triggerManualSprayFromWeb('water')">
                    💧 Semprot Air Manual
                </button>
                <button type="button" class="pesticide-btn" id="btnManualPesticide" onclick="triggerManualSprayFromWeb('pesticide')">
                    🧪 Semprot Pestisida Manual
                </button>
            </div>

            <div class="spray-box">
                <h4>💧 Histori Penyemprotan Terakhir Air (Otomatis Saat Tanah Kering &lt;30% &amp; Manual)</h4>
                <div class="spray-meta" id="waterHistoryBox">Memuat dari TiDB Cloud...</div>
            </div>

            <div class="spray-box" style="margin-bottom:0;">
                <h4 id="pesticideScheduleHeading">🧪 Histori &amp; Jadwal Pestisida (Tiap 5 Hari Sekali)</h4>
                <div class="spray-meta" id="pesticideHistoryBox">Memuat dari TiDB Cloud...</div>
                <div id="pesticidePolicyNote" style="margin-top:8px;padding:8px 11px;border-radius:9px;background:rgba(124, 58, 237, 0.12);color:var(--text-main);font-size:.74rem;font-weight:600;border:1px solid rgba(124, 58, 237, 0.3);">
                    ℹ️ Kebijakan Pestisida &amp; Air: Saat penyakit daun terdeteksi, bot mengirim <strong>Notifikasi Konfirmasi di Telegram</strong>. Penyemprotan otomatis pestisida berjalan <strong>Tiap 5 Hari Sekali (120 Jam)</strong>, sedangkan <strong>Air otomatis menyemprot saat Tanah Kering (&lt;30%)</strong>.
                </div>
            </div>
        </div>

        <!-- Soil Moisture Sensor Card -->
        <div class="card">
            <div class="card-title">
                <div>
                    <h2>🌱 API Sensor Soil Moisture</h2>
                    <p class="card-subtitle">Alur: <code>Web &gt; Chatbot &gt; Database</code></p>
                </div>
            </div>

            <div class="spray-box">
                <h4>🌱 Pembacaan Soil Moisture Terakhir</h4>
                <div class="spray-meta" id="soilDetailBox">Memuat...</div>
            </div>

            <form onsubmit="submitSoilMoisture(event)">
                <label class="field-label" for="inputSoilMoisture">📡 Kirim / Uji Data Soil Moisture (%) ke API</label>
                <div class="toolbar" style="margin-top:6px;">
                    <input type="number" step="0.1" min="0" max="100" id="inputSoilMoisture" value="27.5" required placeholder="0 - 100%">
                    <button type="submit" class="secondary">🌱 Kirim Soil Moisture</button>
                </div>
                <p style="font-size:.75rem;color:var(--text-muted);margin:8px 0 0;">Mengirim ke <code>POST /api/sensor/soil-moisture</code> → langsung memperbarui Web, mengirim notifikasi ke Chatbot, dan menyimpan ke tabel <code>soil_moisture_readings</code>.</p>
            </form>
        </div>

        <!-- DHT22 Sensor Card -->
        <div class="card">
            <div class="card-title">
                <div>
                    <h2>🌡️ API Sensor DHT22</h2>
                    <p class="card-subtitle">Alur: <code>Web &gt; Chatbot &gt; Database</code></p>
                </div>
            </div>

            <div class="spray-box">
                <h4>🌡️ Pembacaan DHT22 Terakhir</h4>
                <div class="spray-meta" id="dhtDetailBox">Memuat...</div>
            </div>

            <form onsubmit="submitDht11(event)">
                <label class="field-label">📡 Kirim / Uji Data Suhu (°C) &amp; Kelembapan Udara (%) ke API</label>
                <div class="toolbar" style="margin-top:6px;">
                    <input type="number" step="0.1" id="inputDhtTemp" value="31.2" required placeholder="Suhu (°C)">
                    <input type="number" step="0.1" min="0" max="100" id="inputDhtHum" value="68.0" required placeholder="Humidity (%)">
                </div>
                <button type="submit" class="secondary" style="margin-top:10px;">🌡️ Kirim Data DHT22</button>
                <p style="font-size:.75rem;color:var(--text-muted);margin:8px 0 0;">Mengirim ke <code>POST /api/sensor/dht22</code> → langsung memperbarui Web, mengirim notifikasi ke Chatbot, dan menyimpan ke tabel <code>dht11_readings</code>.</p>
            </form>
        </div>
    </section>

    <!-- Monitoring Hardware ESP32 & Background Scheduler + Ekspor Laporan -->
    <section class="hardware-banner">
        <div class="card">
            <div class="card-title">
                <div>
                    <h2>🔌 Monitoring Hardware ESP32 &amp; Background Scheduler</h2>
                    <p class="card-subtitle">Status heartbeat mikrokontroler via <code>/api/esp32/poll</code> &amp; pembacaan sensor + pengecekan jadwal latar belakang.</p>
                </div>
                <span class="esp32-pill" id="esp32StatusPill"><span class="esp32-dot"></span><span id="esp32StatusText">Memuat...</span></span>
            </div>
            <div class="spray-box" style="margin-bottom:0;">
                <div class="spray-meta" id="esp32HardwareDetail">Memuat status perangkat ESP32...</div>
                <div class="spray-meta" id="systemServicesSummary" style="margin-top:6px;">Memuat status layanan AI &amp; Bot...</div>
                <p style="font-size:.74rem;color:var(--text-muted);margin:8px 0 0;">
                    ⏱️ Background Scheduler: <code>php artisan schedule:work</code> menjalankan <code>chilivision:check-schedule</code> setiap menit (memicu jadwal pestisida otomatis &amp; mengirim peringatan jika ESP32 offline &gt; 45 detik).
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-title">
                <div>
                    <h2>📑 Unduh Laporan Data (CSV / Excel)</h2>
                    <p class="card-subtitle">Ekspor riwayat sensor, deteksi penyakit daun, dan log penyemprotan langsung dari TiDB Cloud.</p>
                </div>
            </div>
            <div class="export-grid">
                <a class="export-btn" href="api/reports/export/soil" download>📥 Laporan Soil Moisture</a>
                <a class="export-btn" href="api/reports/export/dht22" download>📥 Laporan DHT22 (Suhu)</a>
                <a class="export-btn" href="api/reports/export/leaf" download>📥 Laporan Deteksi Daun</a>
                <a class="export-btn" href="api/reports/export/spray" download>📥 Laporan Penyemprotan</a>
            </div>
        </div>
    </section>

    <!-- Visualisasi Data Tren Sensor & Distribusi Penyakit Daun -->
    <section class="analytics-grid">
        <div class="card">
            <div class="card-title">
                <div>
                    <h2>📈 Grafik Tren Kelembapan Tanah (Soil Moisture)</h2>
                    <p class="card-subtitle">20 pembacaan terakhir (%) beserta ambang batas penyiraman otomatis &lt;30%.</p>
                </div>
            </div>
            <div class="chart-frame">
                <div id="soilChartWrap" class="chart-plot-wrap" tabindex="0" aria-label="Grafik tren kelembapan tanah interaktif">
                    <svg id="soilChartSvg" class="chart-svg" viewBox="0 0 460 195" aria-label="Grafik tren kelembapan tanah"></svg>
                    <div id="soilChartTooltip" class="chart-tooltip" role="tooltip" hidden></div>
                </div>
                <div class="chart-legend" id="soilChartLegend">Memuat data grafik...</div>
                <div class="chart-inspector" id="soilChartInspector">
                    <span id="soilChartReadout">Arahkan kursor atau ketuk grafik untuk melihat detail per titik.</span>
                    <div class="chart-nav-group">
                        <button type="button" class="chart-nav-btn" data-chart-step="soil:-1" title="Titik sebelumnya (Panah Kiri)">◀</button>
                        <button type="button" class="chart-nav-btn" data-chart-step="soil:1" title="Titik berikutnya (Panah Kanan)">▶</button>
                        <button type="button" class="chart-nav-btn" data-chart-reset="soil" title="Reset pilihan titik">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-title">
                <div>
                    <h2>🌡️ Grafik Tren Suhu &amp; Kelembapan Udara (DHT22)</h2>
                    <p class="card-subtitle">20 pembacaan terakhir sensor suhu (°C) dan kelembapan udara (%).</p>
                </div>
            </div>
            <div class="chart-frame">
                <div id="dhtChartWrap" class="chart-plot-wrap" tabindex="0" aria-label="Grafik tren suhu dan kelembapan udara interaktif">
                    <svg id="dhtChartSvg" class="chart-svg" viewBox="0 0 460 195" aria-label="Grafik tren DHT22"></svg>
                    <div id="dhtChartTooltip" class="chart-tooltip" role="tooltip" hidden></div>
                </div>
                <div class="chart-legend" id="dhtChartLegend">Memuat data grafik...</div>
                <div class="chart-inspector" id="dhtChartInspector">
                    <span id="dhtChartReadout">Arahkan kursor atau ketuk grafik untuk melihat detail per titik.</span>
                    <div class="chart-nav-group">
                        <button type="button" class="chart-nav-btn" data-chart-step="dht:-1" title="Titik sebelumnya (Panah Kiri)">◀</button>
                        <button type="button" class="chart-nav-btn" data-chart-step="dht:1" title="Titik berikutnya (Panah Kanan)">▶</button>
                        <button type="button" class="chart-nav-btn" data-chart-reset="dht" title="Reset pilihan titik">Reset</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="card" style="margin-bottom:22px;">
        <div class="card-title">
            <div>
                <h2>📊 Ringkasan Statistik Deteksi Daun &amp; Penyemprotan</h2>
                <p class="card-subtitle">Distribusi kelas deteksi daun yang tersimpan di TiDB Cloud dan total aktivitas penyemprotan.</p>
            </div>
            <span class="badge" id="sprayTotalsBadge">💧 0 Air · 🧪 0 Pestisida</span>
        </div>
        <div id="leafStatsList" class="spray-meta">Memuat ringkasan statistik...</div>
    </section>

    <!-- Endpoint API Documentation Table -->
    <section class="card">
        <div class="card-title">
            <div>
                <h2>🔗 Daftar Lengkap Endpoint REST API Laravel + TiDB Cloud</h2>
                <p class="card-subtitle">Seluruh endpoint aktif dan terhubung ke database TiDB Cloud <code>{{ config('database.connections.mysql.database') }}</code></p>
            </div>
            <a class="badge" href="api" target="_blank">📚 Buka Halaman Dokumentasi Lengkap (/api) ↗</a>
        </div>
        <div style="overflow-x:auto;">
            <table class="api-table">
                <thead>
                <tr>
                    <th>Kategori Endpoint API</th>
                    <th>Method &amp; URL (Beserta Alias)</th>
                    <th>Handler &amp; Alur Data</th>
                    <th>Aksi / JSON</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><strong>0. Info, Health &amp; Ringkasan Dashboard</strong></td>
                    <td>
                        <code>GET /api</code> · <code>GET /api/health</code><br>
                        <code>GET /api/models</code> · <code>GET /api/cameras</code><br>
                        <code>GET /api/dashboard/state</code>
                    </td>
                    <td>Status server Laravel, daftar model ONNX, kamera, dan state gabungan seluruh sensor/histori.</td>
                    <td><a href="api" target="_blank">Buka /api ↗</a></td>
                </tr>
                <tr>
                    <td><strong>1. API Gambar Daun &amp; Deteksi Penyakit</strong></td>
                    <td>
                        <code>GET /api/leaf-image</code> (alias: <code>/api/gambar-daun</code>)<br>
                        <code>POST /api/leaf-image</code> (alias: <code>/api/gambar-daun</code>, <code>/api/predict</code>, <code>/predict</code>)
                    </td>
                    <td>Deteksi terakhir daun; jika daun tidak berubah (kondisi/hash sama), gambar tidak dikirim ulang ke database.</td>
                    <td><a href="api/leaf-image" target="_blank">Buka JSON ↗</a></td>
                </tr>
                <tr>
                    <td><strong>2. API Sensor Soil Moisture</strong></td>
                    <td>
                        <code>GET /api/sensor/soil-moisture</code> (alias: <code>/api/soil-moisture</code>)<br>
                        <code>POST /api/sensor/soil-moisture</code> (alias: <code>/api/soil-moisture</code>)
                    </td>
                    <td>Mengirim data kelembapan tanah terakhir dengan alur <strong>Web &gt; Chatbot &gt; Database</strong> (otomatis semprot air jika &lt;30%).</td>
                    <td><a href="api/sensor/soil-moisture" target="_blank">Buka JSON ↗</a></td>
                </tr>
                <tr>
                    <td><strong>3. API Sensor DHT22 (Suhu &amp; Udara)</strong></td>
                    <td>
                        <code>GET /api/sensor/dht22</code> (alias: <code>/api/dht22</code>, <code>/api/sensor/dht11</code>)<br>
                        <code>POST /api/sensor/dht22</code> (alias: <code>/api/dht22</code>, <code>/api/sensor/dht11</code>)
                    </td>
                    <td>Mengirim data suhu &amp; kelembapan udara terakhir dengan alur <strong>Web &gt; Chatbot &gt; Database</strong>.</td>
                    <td><a href="api/sensor/dht22" target="_blank">Buka JSON ↗</a></td>
                </tr>
                <tr>
                    <td><strong>4. API Histori Penyemprotan Pestisida</strong></td>
                    <td>
                        <code>GET /api/spray/pesticide/history</code> (alias: <code>/api/histori-pestisida</code>)<br>
                        <code>POST /api/spray/pesticide/history</code> (alias: <code>/api/histori-pestisida</code>)
                    </td>
                    <td>Menyimpan &amp; menampilkan waktu terakhir pestisida disemprot beserta jadwal rutin dari tabel <code>spray_histories</code>.</td>
                    <td><a href="api/spray/pesticide/history" target="_blank">Buka JSON ↗</a></td>
                </tr>
                <tr>
                    <td><strong>5. API Histori Penyemprotan Air</strong></td>
                    <td>
                        <code>GET /api/spray/water/history</code> (alias: <code>/api/histori-air</code>)<br>
                        <code>POST /api/spray/water/history</code> (alias: <code>/api/histori-air</code>)
                    </td>
                    <td>Menyimpan &amp; menampilkan waktu terakhir air disemprot dari tabel <code>spray_histories</code>.</td>
                    <td><a href="api/spray/water/history" target="_blank">Buka JSON ↗</a></td>
                </tr>
                <tr>
                    <td><strong>6. API Penyemprotan Manual (Air &amp; Pestisida)</strong></td>
                    <td>
                        <code>POST /api/spray/manual</code> (alias: <code>/api/penyemprotan-manual</code>)
                    </td>
                    <td>Implementasi tombol di web &amp; command manual untuk menyalakan pompa air/pestisida dan memasukkannya ke antrean ESP32.</td>
                    <td><a href="api#group-6_penyemprotan_manual" target="_blank">Uji di /api ↗</a></td>
                </tr>
                <tr>
                    <td><strong>7. API Chatbot, ESP32 Polling &amp; Telegram Webhook</strong></td>
                    <td>
                        <code>GET /api/chatbot/messages</code> · <code>POST /api/chatbot/message</code><br>
                        <code>GET /api/esp32/poll</code> (alias: <code>/api/spray/pending</code>)<br>
                        <code>POST /api/telegram/webhook</code>
                    </td>
                    <td>Sinkronisasi pesan chatbot, polling perintah nyala pompa oleh mikrokontroler ESP32, dan webhook Bot Telegram.</td>
                    <td><a href="api/esp32/poll?preview=1" target="_blank">Cek Poll ↗</a></td>
                </tr>
                </tbody>
            </table>
        </div>
    </section>

    <p class="footer">🌶️ ChiliVision · Laravel + TiDB Cloud Computer Vision &amp; Smart IoT Agriculture System</p>
</main>

<script>
    const BASE_URL = window.location.pathname.startsWith('/chilivision') ? '/chilivision' : '';
    const API_BASE = BASE_URL + '/api';

    const video = document.getElementById("video"),
          uploadedImage = document.getElementById("uploadedImage"),
          overlay = document.getElementById("overlay"),
          overlayContext = overlay.getContext("2d"),
          placeholder = document.getElementById("placeholder"),
          cameraSelect = document.getElementById("cameraSelect"),
          camera = document.getElementById("camera"),
          predict = document.getElementById("predict"),
          upload = document.getElementById("upload"),
          uploadPredict = document.getElementById("uploadPredict"),
          showCamera = document.getElementById("showCamera"),
          model = document.getElementById("model"),
          thresholdSlider = document.getElementById("thresholdSlider"),
          thresholdValueLabel = document.getElementById("thresholdValueLabel"),
          thresholdResetBtn = document.getElementById("thresholdResetBtn"),
          thresholdModeHint = document.getElementById("thresholdModeHint"),
          status = document.getElementById("status"),
          result = document.getElementById("result"),
          dbStatusBanner = document.getElementById("dbStatusBanner");

    let stream = null,
        detectionTimer = null,
        requestInFlight = false,
        uploadObjectUrl = null,
        userCustomThreshold = false;

    function isLiveModel() {
        return model.value === "yolov8n-chili";
    }
    function getRecommendedThreshold() {
        return isLiveModel() ? 0.25 : 0.50;
    }
    function syncSliderVisual() {
        if (!thresholdSlider) return;
        const val = parseFloat(thresholdSlider.value || getRecommendedThreshold());
        const pct = Math.round(val * 100) + "%";
        thresholdSlider.style.setProperty("--attr-min", thresholdSlider.min || 0.10);
        thresholdSlider.style.setProperty("--attr-max", thresholdSlider.max || 0.95);
        thresholdSlider.style.setProperty("--control-value", val);
        thresholdSlider.setAttribute("aria-valuetext", pct);
        if (thresholdValueLabel) {
            thresholdValueLabel.textContent = pct;
        }
        if (thresholdModeHint) {
            thresholdModeHint.textContent = isLiveModel()
                ? `Aktif: ${pct} (Rekomendasi YOLOv8n: 25%)`
                : `Aktif: ${pct} (Rekomendasi CNN: 50%)`;
        }
    }
    function getThreshold() {
        if (thresholdSlider && thresholdSlider.value) {
            return parseFloat(thresholdSlider.value).toFixed(2);
        }
        return getRecommendedThreshold().toFixed(2);
    }
    function updateMode(resetToRecommended = false) {
        if (isLiveModel()) {
            predict.textContent = "🔍 Mulai deteksi langsung";
        } else {
            predict.textContent = "📸 Ambil foto & klasifikasi";
        }
        if (thresholdSlider && (resetToRecommended || !userCustomThreshold)) {
            thresholdSlider.value = getRecommendedThreshold().toFixed(2);
        }
        syncSliderVisual();
        if (detectionTimer) stopDetection();
        clearBoxes();
    }

    if (thresholdSlider) {
        thresholdSlider.addEventListener("input", () => {
            userCustomThreshold = true;
            syncSliderVisual();
        });
    }
    if (thresholdResetBtn) {
        thresholdResetBtn.addEventListener("click", () => {
            userCustomThreshold = false;
            if (thresholdSlider) {
                thresholdSlider.value = getRecommendedThreshold().toFixed(2);
            }
            syncSliderVisual();
        });
    }

    // Set mode awal dari daftar model yang sudah dirender langsung oleh Laravel Blade
    updateMode(true);

    // Sinkronisasi daftar model dari API (aman untuk Port Forwarding / DevTunnels)
    async function syncModels() {
        try {
            const r = await fetch(API_BASE + "/models", {
                headers: { "Accept": "application/json" },
                credentials: "same-origin"
            });
            if (!r.ok) return;
            const items = await r.json();
            if (!Array.isArray(items) || !items.length) return;
            const currentVal = model.value;
            model.innerHTML = "";
            items.forEach(x => {
                const o = document.createElement("option");
                o.value = x.id;
                o.textContent = x.name + (x.available ? "" : " — belum tersedia");
                o.disabled = !x.available;
                model.appendChild(o);
            });
            const keepCurrent = items.find(x => x.id === currentVal && x.available);
            const active = keepCurrent || items.find(x => x.available);
            if (active) model.value = active.id;
            updateMode();
            status.textContent = active ? "✅ Model AI ONNX siap digunakan." : "⚠️ Model belum ditemukan.";
        } catch (err) {
            // Jika tunnel sedang sibuk, tetap gunakan daftar model bawaan yang sudah ter-render di HTML
            console.warn("Menggunakan daftar model SSR:", err);
        }
    }
    syncModels();

    model.addEventListener("change", () => {
        userCustomThreshold = false;
        updateMode(true);
    });

    async function loadCameras() {
        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
                cameraSelect.innerHTML = "<option value=''>Browser memblokir kamera (gunakan localhost atau HTTPS)</option>";
                return;
            }
            const prevValue = cameraSelect.value;
            const devices = await navigator.mediaDevices.enumerateDevices();
            const cameras = devices.filter(x => x.kind === "videoinput");
            cameraSelect.innerHTML = "";
            cameras.forEach((x, index) => {
                const o = document.createElement("option");
                o.value = x.deviceId;
                o.textContent = x.label || `Kamera ${index + 1}`;
                cameraSelect.appendChild(o);
            });
            if (prevValue && cameras.some(c => c.deviceId === prevValue)) {
                cameraSelect.value = prevValue;
            }
            if (!cameras.length) cameraSelect.innerHTML = "<option value=''>Kamera tidak terdeteksi</option>";
        } catch (e) {
            cameraSelect.innerHTML = "<option value=''>Izinkan akses kamera di browser</option>";
        }
    }
    loadCameras();
    if (navigator.mediaDevices && navigator.mediaDevices.addEventListener) {
        navigator.mediaDevices.addEventListener("devicechange", loadCameras);
    }

    async function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error("Akses kamera membutuhkan localhost, 127.0.0.1, atau HTTPS.");
        }
        resetPreview();
        const deviceId = cameraSelect.value;
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: deviceId ? { deviceId: { exact: deviceId } } : true
            });
        } catch (err) {
            if (deviceId && (err.name === "OverconstrainedError" || err.name === "NotFoundError")) {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
            } else {
                throw err;
            }
        }
        video.srcObject = stream;
        await video.play().catch(() => {});
        video.hidden = false;
        uploadedImage.hidden = true;
        overlay.hidden = false;
        placeholder.hidden = true;
        showCamera.hidden = true;
        predict.disabled = false;
        camera.textContent = "⏹️ Matikan kamera";
        camera.onclick = stop;
    }

    function formatCameraError(e) {
        if (!e) return "Kamera tidak dapat diakses.";
        if (e.name === "NotAllowedError" || e.name === "PermissionDeniedError") {
            return "Izin kamera ditolak browser. Klik ikon gembok/kamera di address bar lalu pilih Allow.";
        }
        if (e.name === "NotReadableError" || e.name === "TrackStartError") {
            return "Webcam sedang dipakai aplikasi lain (Zoom/Meet/Camera/OBS) atau driver bermasalah.";
        }
        if (e.name === "NotFoundError" || e.name === "DevicesNotFoundError") {
            return "Webcam tidak ditemukan oleh browser. Cek kabel USB atau Device Manager.";
        }
        return e.message || "Kamera tidak dapat diakses.";
    }

    camera.onclick = async () => {
        try {
            await startCamera();
            await loadCameras();
        } catch (e) {
            status.textContent = formatCameraError(e);
        }
    };

    function stopCameraForUpload() {
        stopDetection();
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        video.pause();
        video.srcObject = null;
        video.hidden = true;
        predict.disabled = true;
        camera.textContent = "📷 Aktifkan kamera";
        camera.onclick = async () => {
            try { await startCamera(); } catch (e) { status.textContent = "Kamera tidak dapat diakses."; }
        };
    }

    function resetPreview() {
        stopCameraForUpload();
        if (uploadObjectUrl) {
            URL.revokeObjectURL(uploadObjectUrl);
            uploadObjectUrl = null;
        }
        uploadedImage.removeAttribute("src");
        uploadedImage.hidden = true;
        video.hidden = true;
        overlay.hidden = true;
        placeholder.hidden = false;
        showCamera.hidden = true;
        clearBoxes();
        upload.value = "";
    }

    function stop() { resetPreview(); }
    function clearBoxes() { overlayContext.clearRect(0, 0, overlay.width, overlay.height); }

    function formatDiseaseName(name) {
        const raw = String(name || '').trim();
        if (!raw) return '';
        if (raw.includes('_')) {
            return raw.replace(/_/g, ' ').replace(/\s+/g, ' ').trim()
                .replace(/\b\w/g, c => c.toUpperCase());
        }
        return raw;
    }

    function drawBoxes(detections, width, height) {
        const overlayRect = overlay.getBoundingClientRect(),
              target = uploadedImage.hidden ? video : uploadedImage,
              targetRect = target.getBoundingClientRect();
        overlay.width = Math.round(overlayRect.width);
        overlay.height = Math.round(overlayRect.height);
        clearBoxes();
        if (!detections || !detections.length) return;

        const boxAspect = targetRect.width / targetRect.height,
              imageAspect = width / height;
        let contentWidth = targetRect.width,
            contentHeight = targetRect.height,
            contentX = targetRect.left - overlayRect.left,
            contentY = targetRect.top - overlayRect.top;
        if (imageAspect > boxAspect) {
            contentHeight = contentWidth / imageAspect;
            contentY += (targetRect.height - contentHeight) / 2;
        } else {
            contentWidth = contentHeight * imageAspect;
            contentX += (targetRect.width - contentWidth) / 2;
        }
        const targetRight = contentX + contentWidth,
              targetBottom = contentY + contentHeight,
              scaleX = contentWidth / width,
              scaleY = contentHeight / height;

        overlayContext.lineWidth = 3;
        overlayContext.font = "700 13px 'Plus Jakarta Sans', Inter, sans-serif";
        detections.forEach((x, index) => {
            if (!x.bbox_xyxy) return;
            const [x1, y1, x2, y2] = x.bbox_xyxy;
            const left = Math.max(contentX, Math.min(targetRight, contentX + x1 * scaleX)),
                  top = Math.max(contentY, Math.min(targetBottom, contentY + y1 * scaleY)),
                  right = Math.max(left, Math.min(targetRight, contentX + x2 * scaleX)),
                  bottom = Math.max(top, Math.min(targetBottom, contentY + y2 * scaleY));
            const text = `${index + 1}. ${formatDiseaseName(x.class_name)} ${(x.confidence * 100).toFixed(1)}%`;
            overlayContext.strokeStyle = "#49c778";
            overlayContext.strokeRect(left, top, right - left, bottom - top);
            const textWidth = overlayContext.measureText(text).width + 18,
                  labelHeight = 25,
                  labelX = Math.min(Math.max(contentX, left), Math.max(contentX, targetRight - textWidth)),
                  aboveY = top - labelHeight,
                  labelY = aboveY >= contentY ? aboveY : Math.min(bottom, targetBottom - labelHeight);
            overlayContext.fillStyle = "#49c778";
            overlayContext.fillRect(labelX, labelY, textWidth, labelHeight);
            overlayContext.fillStyle = "#092315";
            overlayContext.fillText(text, labelX + 9, labelY + 17);
        });
    }

    function updateLeafDbBanner(payload) {
        if (payload.saved_to_database) {
            dbStatusBanner.className = "db-banner saved";
            dbStatusBanner.textContent = "✅ DAUN BERUBAH: " + payload.database_message;
        } else {
            dbStatusBanner.className = "db-banner skipped";
            dbStatusBanner.textContent = "⏭️ DAUN TIDAK BERUBAH: " + (payload.database_message || "Gambar tidak dikirim ulang ke database TiDB Cloud.");
        }
        refreshDashboardState();
    }

    async function detectFrame() {
        if (requestInFlight || !stream) return;
        requestInFlight = true;
        try {
            if (video.readyState < 2 || !video.videoWidth) {
                status.textContent = "Menunggu kamera siap...";
                return;
            }
            const maxW = 640;
            const scale = video.videoWidth > maxW ? (maxW / video.videoWidth) : 1;
            const canvas = document.createElement("canvas");
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise(r => canvas.toBlob(r, "image/jpeg", .80));
            if (!blob) throw new Error("Gambar kamera tidak dapat dibuat.");
            const data = new FormData();
            data.append("file", blob, "webcam.jpg");
            const response = await fetch(API_BASE + "/leaf-image?model_id=" + encodeURIComponent(model.value) + "&threshold=" + getThreshold(), {
                method: "POST",
                body: data
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.detail || "Deteksi gagal.");
            drawBoxes(payload.detections, payload.image_width, payload.image_height);
            updateLeafDbBanner(payload);

            if (!payload.detections || !payload.detections.length) {
                result.textContent = "Tidak ada deteksi di atas threshold.";
                status.textContent = "Mencari objek daun...";
            } else {
                const primary = payload.primary_detection;
                result.innerHTML = `<div class="detection"><span>Hasil utama: <strong>${escapeHtml(formatDiseaseName(primary.class_name))}</strong></span><span class="confidence">${(primary.confidence * 100).toFixed(1)}%</span></div>`
                    + payload.detections.slice(1).map(x => `<div class="detection"><span>${escapeHtml(formatDiseaseName(x.class_name))}</span><span class="confidence">${(x.confidence * 100).toFixed(1)}%</span></div>`).join("");
                status.textContent = `${payload.detections.length} objek daun terdeteksi.`;
            }
        } catch (e) {
            status.textContent = e.message;
        } finally {
            requestInFlight = false;
        }
    }

    async function capturePhotoAndDetect() {
        if (requestInFlight || !stream) return;
        requestInFlight = true;
        try {
            if (video.readyState < 2 || !video.videoWidth) return;
            const canvas = document.createElement("canvas");
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext("2d").drawImage(video, 0, 0);
            const blob = await new Promise(r => canvas.toBlob(r, "image/jpeg", .92));
            if (uploadObjectUrl) URL.revokeObjectURL(uploadObjectUrl);
            uploadedImage.src = uploadObjectUrl = URL.createObjectURL(blob);
            uploadedImage.hidden = false;
            video.hidden = true;
            overlay.hidden = false;
            placeholder.hidden = true;
            showCamera.hidden = false;
            stream.getTracks().forEach(t => t.stop());
            stream = null;
            video.pause();
            video.srcObject = null;
            predict.disabled = true;
            camera.textContent = "🔄 Reset tampilan";
            camera.onclick = resetPreview;

            status.textContent = "Menganalisis kondisi daun...";
            const data = new FormData();
            data.append("file", blob, "capture.jpg");
            const response = await fetch(API_BASE + "/leaf-image?model_id=" + encodeURIComponent(model.value) + "&threshold=" + getThreshold(), {
                method: "POST",
                body: data
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.detail || "Deteksi gagal.");
            updateLeafDbBanner(payload);

            if (payload.classification) {
                const c = payload.classification;
                result.innerHTML = `<div class="detection"><span>Klasifikasi: <strong>${escapeHtml(formatDiseaseName(c.class_name))}</strong></span><span class="confidence">${(c.confidence * 100).toFixed(1)}%</span></div>`;
                status.textContent = "Klasifikasi daun selesai.";
            } else {
                if (!uploadedImage.complete || !uploadedImage.naturalWidth) {
                    await new Promise(r => { uploadedImage.onload = r; uploadedImage.onerror = r; });
                }
                drawBoxes(payload.detections, payload.image_width, payload.image_height);
                if (!payload.detections || !payload.detections.length) {
                    const pct = Math.round(parseFloat(getThreshold()) * 100) + "%";
                    result.textContent = `Tidak ada objek/klasifikasi daun di atas threshold (${pct}).`;
                    status.textContent = "Tidak ada objek terdeteksi di atas threshold.";
                }
            }
        } catch (e) {
            status.textContent = e.message;
        } finally {
            requestInFlight = false;
        }
    }

    function startDetection() {
        if (detectionTimer) return;
        predict.textContent = "⏸️ Hentikan deteksi langsung";
        detectFrame();
        detectionTimer = setInterval(detectFrame, 500);
    }
    function stopDetection() {
        if (detectionTimer) {
            clearInterval(detectionTimer);
            detectionTimer = null;
        }
        predict.textContent = isLiveModel() ? "🔍 Mulai deteksi langsung" : "📸 Ambil foto & klasifikasi";
        clearBoxes();
    }

    predict.onclick = () => isLiveModel() ? (detectionTimer ? stopDetection() : startDetection()) : capturePhotoAndDetect();

    upload.addEventListener("change", () => {
        const file = upload.files[0];
        if (!file) return;
        stopCameraForUpload();
        if (uploadObjectUrl) URL.revokeObjectURL(uploadObjectUrl);
        uploadedImage.src = uploadObjectUrl = URL.createObjectURL(file);
        uploadedImage.hidden = false;
        placeholder.hidden = true;
        overlay.hidden = false;
        showCamera.hidden = false;
        clearBoxes();
        status.textContent = "Gambar dipilih. Klik 'Upload & Analisis Daun' untuk mendeteksi.";
    });

    uploadPredict.onclick = async () => {
        const file = upload.files[0];
        if (!file) {
            status.textContent = "Pilih file gambar daun terlebih dahulu.";
            return;
        }
        stopCameraForUpload();
        if (uploadObjectUrl) URL.revokeObjectURL(uploadObjectUrl);
        uploadedImage.src = uploadObjectUrl = URL.createObjectURL(file);
        uploadedImage.hidden = false;
        placeholder.hidden = true;
        overlay.hidden = false;
        showCamera.hidden = false;
        status.textContent = "Mengirim gambar ke API Gambar Daun Laravel...";

        let uploadBlob = file;
        let uploadName = file.name || "leaf.jpg";
        if (file.size > 1500000) {
            try {
                if (!uploadedImage.complete || !uploadedImage.naturalWidth) {
                    await new Promise(r => { uploadedImage.onload = r; uploadedImage.onerror = r; });
                }
                const natW = uploadedImage.naturalWidth || 1280;
                const natH = uploadedImage.naturalHeight || 960;
                const maxSide = 1280;
                const ratio = Math.min(1, maxSide / Math.max(natW, natH));
                const c = document.createElement("canvas");
                c.width = Math.max(1, Math.round(natW * ratio));
                c.height = Math.max(1, Math.round(natH * ratio));
                c.getContext("2d").drawImage(uploadedImage, 0, 0, c.width, c.height);
                const compressed = await new Promise(r => c.toBlob(r, "image/jpeg", 0.90));
                if (compressed && compressed.size > 0) {
                    uploadBlob = compressed;
                    uploadName = uploadName.replace(/\.[^.]+$/, "") + ".jpg";
                }
            } catch (_) {}
        }

        const data = new FormData();
        data.append("file", uploadBlob, uploadName);
        try {
            const response = await fetch(API_BASE + "/leaf-image?model_id=" + encodeURIComponent(model.value) + "&threshold=" + getThreshold(), {
                method: "POST",
                body: data
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.detail || "Deteksi gagal.");
            if (!uploadedImage.complete || !uploadedImage.naturalWidth) {
                await new Promise(r => { uploadedImage.onload = r; uploadedImage.onerror = r; });
            }
            drawBoxes(payload.detections || [], payload.image_width, payload.image_height);
            updateLeafDbBanner(payload);

            if (payload.classification) {
                const c = payload.classification;
                result.innerHTML = `<div class="detection"><span>Hasil klasifikasi: <strong>${escapeHtml(formatDiseaseName(c.class_name))}</strong></span><span class="confidence">${(c.confidence * 100).toFixed(1)}%</span></div>`;
                status.textContent = "Foto berhasil diklasifikasikan.";
            } else if (!payload.detections || !payload.detections.length) {
                result.textContent = "Upload: tidak ada deteksi daun di atas threshold.";
                status.textContent = "Tidak ada objek terdeteksi.";
            } else {
                const primary = payload.primary_detection;
                result.innerHTML = `<div class="detection"><span>Hasil utama: <strong>${escapeHtml(formatDiseaseName(primary.class_name))}</strong></span><span class="confidence">${(primary.confidence * 100).toFixed(1)}%</span></div>`
                    + payload.detections.slice(1).map(x => `<div class="detection"><span>${escapeHtml(formatDiseaseName(x.class_name))}</span><span class="confidence">${(x.confidence * 100).toFixed(1)}%</span></div>`).join("");
                status.textContent = `${payload.detections.length} objek daun terdeteksi.`;
            }
        } catch (e) {
            status.textContent = e.message;
        }
    };

    showCamera.onclick = async () => {
        try { await startCamera(); } catch (e) { status.textContent = "Kamera tidak dapat diakses."; }
    };

