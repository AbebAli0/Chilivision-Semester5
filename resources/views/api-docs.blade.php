<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light dark">
    <script>
        try {
            document.querySelector('meta[name="color-scheme"]').content = localStorage.getItem("color-scheme") ?? "light dark";
        } catch (e) {}
    </script>
    <title>{{ $name }} · Dokumentasi &amp; Playground API</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/chilivision-logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @property --bg { syntax: '<color>'; inherits: true; initial-value: #f4f8f4; }
        @property --bg-grad-start { syntax: '<color>'; inherits: true; initial-value: #f7fbf6; }
        @property --bg-grad-mid { syntax: '<color>'; inherits: true; initial-value: #edf6ed; }
        @property --bg-grad-end { syntax: '<color>'; inherits: true; initial-value: #dfefe2; }
        @property --surface { syntax: '<color>'; inherits: true; initial-value: #ffffff; }
        @property --surface-alt { syntax: '<color>'; inherits: true; initial-value: #f8fbf8; }
        @property --topbar-bg { syntax: '<color>'; inherits: true; initial-value: rgba(255, 255, 255, 0.9); }
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
            --topbar-bg-light: rgba(255, 255, 255, 0.9);
            --topbar-bg-dark: rgba(18, 33, 24, 0.9);

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
        :root.theme-fallback-transition .stat-pill,
        :root.theme-fallback-transition .filter-card,
        :root.theme-fallback-transition .search-box input,
        :root.theme-fallback-transition .filter-btn,
        :root.theme-fallback-transition .api-group-card,
        :root.theme-fallback-transition .group-header,
        :root.theme-fallback-transition .endpoint-card,
        :root.theme-fallback-transition .endpoint-head,
        :root.theme-fallback-transition .endpoint-body,
        :root.theme-fallback-transition .playground-card,
        :root.theme-fallback-transition .playground-header,
        :root.theme-fallback-transition .playground-body,
        :root.theme-fallback-transition .field-input,
        :root.theme-fallback-transition .field-textarea,
        :root.theme-fallback-transition .field-select,
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
            max-width: 1380px;
            margin: 0 auto;
            padding: 24px 22px 64px;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 26px;
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
            font-size: 1.12rem;
            text-decoration: none;
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
        .brand-sub {
            font-size: .76rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 2px;
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
            padding: 8px 14px;
            border-radius: 99px;
            background: var(--mint);
            color: var(--green);
            text-decoration: none;
            border: 1px solid rgba(23, 114, 69, 0.14);
            transition: transform .15s, box-shadow .15s;
        }
        a.badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 42, 29, 0.09);
        }
        .badge.primary-action {
            background: linear-gradient(135deg, #177245 0%, #0f5a35 100%);
            color: #ffffff;
            border-color: transparent;
        }
        .badge.mysql {
            background: var(--blue-bg);
            color: var(--blue-hover);
            border-color: rgba(3, 105, 161, 0.18);
        }
        .badge.laravel {
            background: rgba(239, 68, 68, 0.14);
            color: #ef4444;
            border-color: rgba(185, 28, 28, 0.2);
        }
        button.theme-toggle-btn {
            appearance: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 99px;
            font: inherit;
            font-size: .78rem;
            font-weight: 700;
            background: var(--surface-alt);
            color: var(--dark);
            border: 1px solid var(--line);
            cursor: pointer;
            user-select: none;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
            transition: transform 0.1s ease, box-shadow 0.1s ease, background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }
        button.theme-toggle-btn:hover {
            background: var(--mint);
            color: var(--green);
            border-color: var(--green);
            transform: none;
        }
        button.theme-toggle-btn:active,
        button.theme-toggle-btn.is-pressed {
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
            margin-bottom: 22px;
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
        .eyebrow::before {
            content: "";
            width: 22px;
            height: 2.5px;
            border-radius: 99px;
            background: var(--green);
        }
        .hero h1 {
            font-size: clamp(1.65rem, 2.8vw, 2.35rem);
            line-height: 1.15;
            letter-spacing: -.035em;
            margin: 8px 0 6px;
            color: var(--dark);
            font-weight: 800;
        }
        .hero-desc {
            margin: 0;
            color: var(--text-muted);
            font-size: .92rem;
            line-height: 1.55;
            max-width: 780px;
        }

        /* Summary KPI Row */
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
            padding: 16px 20px;
            box-shadow: 0 10px 26px rgba(16, 42, 29, 0.045);
        }
        .kpi-label {
            font-size: .74rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 6px;
        }
        .kpi-val {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 4px;
            font-variant-numeric: tabular-nums;
        }
        .kpi-sub {
            font-size: .78rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        /* Search & Filter Bar */
        .filter-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 16px 20px;
            margin-bottom: 22px;
            box-shadow: 0 10px 26px rgba(16, 42, 29, 0.04);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
        }
        .search-box {
            flex: 1 1 320px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .search-box label {
            font-size: .82rem;
            font-weight: 700;
            color: var(--dark);
            white-space: nowrap;
        }
        .search-input {
            width: 100%;
            border-radius: 11px;
            border: 1px solid var(--line);
            padding: 10px 14px;
            font: inherit;
            font-size: .86rem;
            color: var(--text-main);
            background: var(--surface-alt);
        }
        .search-input:focus {
            outline: 2px solid rgba(23, 114, 69, 0.25);
            border-color: var(--green);
            background: var(--surface);
        }
        .method-filters {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-btn {
            appearance: none;
            border: 1px solid var(--line);
            background: var(--surface-alt);
            color: var(--text-main);
            font: inherit;
            font-size: .78rem;
            font-weight: 700;
            padding: 8px 14px;
            border-radius: 99px;
            cursor: pointer;
            transition: all .15s;
        }
        .filter-btn:hover {
            background: var(--mint);
            border-color: var(--green);
            color: var(--green);
        }
        .filter-btn.active {
            background: #177245;
            border-color: #177245;
            color: #ffffff;
        }
        .group-nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding-top: 8px;
            border-top: 1px solid var(--line);
        }
        .group-chip {
            text-decoration: none;
            font-size: .75rem;
            font-weight: 700;
            color: var(--text-main);
            background: var(--surface-alt);
            border: 1px solid var(--line);
            padding: 6px 12px;
            border-radius: 99px;
            transition: all .15s;
        }
        .group-chip:hover {
            background: var(--mint);
            color: var(--green);
            border-color: var(--green);
        }

        /* Group Cards & Responsive Tables */
        .api-group-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 12px 32px rgba(16, 42, 29, 0.05);
            scroll-margin-top: 24px;
        }
        .group-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .group-header h2 {
            margin: 0;
            font-size: 1.14rem;
            font-weight: 800;
            color: var(--dark);
            letter-spacing: -.02em;
        }
        .group-count {
            font-size: .74rem;
            font-weight: 700;
            padding: 5px 11px;
            border-radius: 99px;
            background: var(--mint);
            color: var(--green);
            border: 1px solid var(--line);
        }
        .group-note {
            margin: 0 0 16px;
            padding: 10px 14px;
            border-radius: 12px;
            background: var(--surface-alt);
            border-left: 4px solid var(--green);
            color: var(--text-main);
            font-size: .82rem;
            line-height: 1.5;
        }

        .table-wrapper {
            overflow: auto;
            max-inline-size: 100%;
            container-type: inline-size;
            border: 1px solid var(--line);
            border-radius: 14px;
        }
        table.api-spec-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: .83rem;
        }
        table.api-spec-table thead {
            position: sticky;
            inset-block-start: 0;
            z-index: 1;
        }
        table.api-spec-table th,
        table.api-spec-table td {
            text-align: left;
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
        }
        table.api-spec-table thead th {
            background: var(--surface-alt);
            color: var(--dark);
            font-weight: 800;
            font-size: .77rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            white-space: nowrap;
        }
        table.api-spec-table tbody tr:last-child th,
        table.api-spec-table tbody tr:last-child td {
            border-bottom: none;
        }
        table.api-spec-table tbody tr:hover {
            background: var(--surface-alt);
        }
        table.api-spec-table tbody th {
            background: inherit;
            font-weight: 700;
            width: 90px;
        }

        .method-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 58px;
            padding: 4px 10px;
            border-radius: 7px;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: .74rem;
            font-weight: 700;
            letter-spacing: .03em;
        }
        .method-badge.method-get {
            background: var(--mint);
            color: var(--green);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .method-badge.method-post {
            background: var(--blue-bg);
            color: var(--blue-hover);
            border: 1px solid rgba(56, 189, 248, 0.3);
        }

        code {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            background: var(--code-bg);
            padding: 2px 7px;
            border-radius: 6px;
            font-size: .78rem;
            color: var(--code-text);
            font-weight: 600;
        }
        .endpoint-primary {
            font-size: .83rem;
            background: var(--code-bg);
            color: var(--code-text);
            padding: 4px 8px;
            display: inline-block;
            margin-bottom: 4px;
        }
        .alias-list {
            margin-top: 5px;
            font-size: .75rem;
            color: var(--text-muted);
            line-height: 1.6;
        }
        .alias-list code {
            font-size: .72rem;
            background: var(--surface-alt);
            color: var(--text-muted);
            font-weight: 500;
        }
        .handler-tag {
            display: inline-block;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: .75rem;
            color: #a78bfa;
            background: rgba(124, 58, 237, 0.14);
            border: 1px solid rgba(124, 58, 237, 0.3);
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
        }
        .param-text {
            white-space: pre-line;
            font-size: .79rem;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 6px;
        }
        .desc-text {
            font-size: .81rem;
            color: var(--text-main);
            line-height: 1.5;
            margin-bottom: 6px;
            font-weight: 500;
        }
        .action-cell {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 125px;
        }
        .btn-try {
            appearance: none;
            border: 1px solid rgba(23, 114, 69, 0.25);
            background: var(--mint);
            color: var(--green);
            font: inherit;
            font-size: .75rem;
            font-weight: 700;
            padding: 7px 11px;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: all .15s;
        }
        .btn-try:hover {
            background: #177245;
            color: #ffffff;
        }
        .btn-open-json {
            font-size: .73rem;
            font-weight: 700;
            color: var(--blue-hover);
            background: var(--blue-bg);
            border: 1px solid rgba(3, 105, 161, 0.2);
            padding: 6px 10px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            transition: all .15s;
        }
        .btn-open-json:hover {
            background: #0284c7;
            color: #ffffff;
        }

        /* Native Dialog Modal for Interactive API Playground */
        dialog#apiTesterDialog {
            width: min(760px, 94vw);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 0;
            background: var(--surface);
            color: var(--dark);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
        }
        dialog#apiTesterDialog::backdrop {
            background: rgba(10, 20, 14, 0.6);
            backdrop-filter: blur(4px);
        }
        .dialog-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 18px 22px;
            border-bottom: 1px solid var(--line);
            background: var(--surface-alt);
        }
        .dialog-header h2 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--dark);
        }
        .dialog-close-btn {
            appearance: none;
            border: 1px solid var(--line);
            background: var(--surface);
            color: var(--dark);
            font: inherit;
            font-size: .8rem;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
        }
        .dialog-close-btn:hover {
            background: rgba(239, 68, 68, 0.16);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.35);
        }
        .dialog-body {
            padding: 20px 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .tester-bar {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .tester-url {
            flex: 1 1 280px;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: .83rem;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: var(--surface-alt);
            color: var(--dark);
        }
        .tester-send-btn {
            appearance: none;
            border: none;
            background: linear-gradient(135deg, #177245 0%, #0f5a35 100%);
            color: #ffffff;
            font: inherit;
            font-size: .84rem;
            font-weight: 700;
            padding: 10px 18px;
            border-radius: 10px;
            cursor: pointer;
        }
        .tester-send-btn:disabled {
            opacity: .6;
            cursor: not-allowed;
        }
        .tester-field-label {
            display: block;
            font-size: .78rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
        }
        .tester-textarea {
            width: 100%;
            min-height: 120px;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: .8rem;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: var(--surface-alt);
            color: var(--dark);
            resize: vertical;
        }
        .tester-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .78rem;
            font-weight: 700;
            color: var(--text-muted);
        }
        pre.response-pre {
            margin: 0;
            padding: 14px;
            border-radius: 12px;
            background: #0f2419;
            color: #d9fbe5;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: .78rem;
            line-height: 1.55;
            max-height: 320px;
            overflow: auto;
            border: 1px solid #1e422f;
        }
        pre.response-pre code {
            background: transparent;
            color: inherit;
            padding: 0;
            font-size: inherit;
            font-weight: 400;
        }

        .footer {
            color: var(--text-muted);
            text-align: center;
            font-size: .82rem;
            margin-top: 34px;
            font-weight: 600;
        }

        @container (width < 700px) {
            table.api-spec-table,
            table.api-spec-table thead,
            table.api-spec-table tbody,
            table.api-spec-table tr,
            table.api-spec-table th,
            table.api-spec-table td {
                display: block;
            }
            table.api-spec-table thead {
                display: none;
            }
            table.api-spec-table tbody tr {
                border-bottom: 2px solid var(--line);
                padding: 8px 0;
            }
            table.api-spec-table tbody th,
            table.api-spec-table tbody td {
                width: 100%;
                border-bottom: none;
                padding: 6px 14px;
            }
            .action-cell {
                flex-direction: row;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 1024px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 600px) {
            .kpi-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="shell">
    <header>
        <nav class="topbar" aria-label="Navigasi Utama Dokumentasi API">
            <a href="{{ url('/') }}" class="brand">
                <span class="brand-mark" aria-hidden="true" style="padding:0;overflow:hidden;"><img src="{{ asset('images/chilivision-logo.svg') }}" alt="Logo ChiliVision" style="width:100%;height:100%;object-fit:cover;display:block;"></span>
                <div>
                    <div>Chili Vision · REST API Documentation</div>
                    <div class="brand-sub">{{ $framework }} · Database TiDB Cloud ({{ $database }})</div>
                </div>
            </a>
            <div class="top-badges">
                <button type="button" id="themeToggleBtn" class="theme-toggle-btn" aria-label="Ubah Tema Terang atau Gelap">
                    <span class="if-light">🌙 Dark Mode</span>
                    <span class="if-dark">☀️ Light Mode</span>
                </button>
                <a class="badge primary-action" href="{{ url('/') }}">🏠 Kembali ke Dashboard Utama</a>
                <a class="badge mysql" href="{{ url('/api?format=json') }}" target="_blank" rel="noopener">📄 Raw JSON Spec ↗</a>
                <a class="badge" href="{{ url('/api/dashboard/state') }}" target="_blank" rel="noopener">📡 Live State JSON ↗</a>
                <a class="badge laravel" href="{{ url('/api/health') }}" target="_blank" rel="noopener">💚 Health Check ↗</a>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div>
                <span class="eyebrow">RESTful API Specification · Playground Interaktif</span>
                <h1>Daftar Lengkap Endpoint API ChiliVision</h1>
                <p class="hero-desc">
                    Seluruh endpoint didefinisikan di <code>routes/api.php</code> (dengan prefix <code>/api</code>) serta kompatibilitas root di <code>routes/web.php</code>, ditangani oleh <code>ChiliVisionApiController</code> bersama service layer modular (<code>LeafInferenceService</code>, <code>PesticideScheduleService</code>, <code>GeminiChatService</code>). Klik <strong>⚡ Uji Endpoint</strong> pada baris mana pun untuk mengetes request secara langsung.
                </p>
            </div>
        </section>

        <!-- Summary Cards -->
        <section class="kpi-grid" aria-label="Ringkasan Statistik API">
            <div class="kpi-card">
                <div class="kpi-label">🔗 Base Endpoint URL</div>
                <div class="kpi-val"><code>/api</code></div>
                <div class="kpi-sub">{{ $base_url }}</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">🗂️ Total Kategori &amp; Rute</div>
                <div class="kpi-val">{{ $total_groups }} Grup · {{ $total_primary_endpoints }} Endpoint</div>
                <div class="kpi-sub">{{ $total_routes_including_aliases }} rute aktif (termasuk alias bahasa Indonesia &amp; root)</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">🗄️ Koneksi Database &amp; Service</div>
                <div class="kpi-val">TiDB Cloud ({{ $database }})</div>
                <div class="kpi-sub">Service: <code>LeafInferenceService</code>, <code>PesticideScheduleService</code>, <code>GeminiChatService</code></div>
            </div>
            <div class="kpi-card">
                @php
                    $engineMode = ($system_services['inference_engine']['mode'] ?? 'cli_fallback') === 'fastapi_warm'
                        ? 'FastAPI Warm (8001)'
                        : 'CLI Fallback';
                    $geminiLabel = ($system_services['gemini_ai']['enabled'] ?? false)
                        ? ($system_services['gemini_ai']['model'] ?? 'Gemini')
                        : 'Nonaktif';
                @endphp
                <div class="kpi-label">🧠 Engine AI &amp; Layanan</div>
                <div class="kpi-val">{{ count($available_models) }} Model · {{ $engineMode }}</div>
                <div class="kpi-sub">Model: {{ implode(', ', $available_models) ?: 'Tidak ada' }} · Gemini: {{ $geminiLabel }}</div>
            </div>
        </section>

        <!-- Search & Filter Section -->
        <search class="filter-card" aria-label="Pencarian dan Filter Endpoint API">
            <div class="filter-row">
                <div class="search-box">
                    <label for="apiSearchInput">🔍 Cari Endpoint:</label>
                    <input
                        type="search"
                        id="apiSearchInput"
                        class="search-input"
                        placeholder="Ketik URL, nama fungsi, sensor, atau parameter (misal: /api/leaf-image, dht22, manual, esp32)..."
                    >
                </div>
                <div class="method-filters" role="group" aria-label="Filter berdasarkan HTTP Method">
                    <button type="button" class="filter-btn active" data-method-filter="ALL">Semua ({{ $total_primary_endpoints }})</button>
                    <button type="button" class="filter-btn" data-method-filter="GET">GET</button>
                    <button type="button" class="filter-btn" data-method-filter="POST">POST</button>
                </div>
            </div>
            <nav class="group-nav" aria-label="Lompat ke Kategori API">
                @foreach($groups as $group)
                    <a href="#group-{{ $group['group_key'] }}" class="group-chip">
                        <span aria-hidden="true">{{ $group['icon'] }}</span> {{ $group['title'] }}
                    </a>
                @endforeach
            </nav>
        </search>

        <!-- All 8 API Groups (0 to 7) -->
        @foreach($groups as $group)
            <section class="api-group-card" id="group-{{ $group['group_key'] }}" data-group-section>
                <div class="group-header">
                    <h2 id="heading-{{ $group['group_key'] }}">
                        <span aria-hidden="true">{{ $group['icon'] }}</span> {{ $group['title'] }}
                    </h2>
                    <span class="group-count">{{ count($group['items']) }} Endpoint</span>
                </div>
                <p class="group-note">{{ $group['note'] }}</p>

                <div tabindex="0" role="region" aria-labelledby="heading-{{ $group['group_key'] }}" class="table-wrapper">
                    <table class="api-spec-table">
                        <thead>
                        <tr>
                            <th scope="col">Method</th>
                            <th scope="col">Endpoint &amp; Alias</th>
                            <th scope="col">Handler</th>
                            <th scope="col">Deskripsi &amp; Parameter / Body</th>
                            <th scope="col">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($group['items'] as $idx => $item)
                            @php
                                $methodLower = strtolower($item['method']);
                                $sampleJson = $item['sample_payload'] !== null
                                    ? json_encode($item['sample_payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                                    : '';
                                $isMultipartLeaf = ($item['endpoint'] === '/api/leaf-image' && $item['method'] === 'POST');
                            @endphp
                            <tr
                                data-api-row
                                data-method="{{ $item['method'] }}"
                                data-search="{{ strtolower($item['method'] . ' ' . $item['endpoint'] . ' ' . implode(' ', $item['aliases']) . ' ' . $item['handler'] . ' ' . $item['description'] . ' ' . $item['parameters']) }}"
                            >
                                <th scope="row">
                                    <span class="method-badge method-{{ $methodLower }}">{{ $item['method'] }}</span>
                                </th>
                                <td>
                                    <code class="endpoint-primary">{{ $item['endpoint'] }}</code>
                                    @if(!empty($item['aliases']))
                                        <div class="alias-list">
                                            Alias:
                                            @foreach($item['aliases'] as $alias)
                                                <code>{{ $alias }}</code>@if(!$loop->last), @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="handler-tag">{{ $item['handler'] }}</span>
                                </td>
                                <td>
                                    <div class="desc-text">{{ $item['description'] }}</div>
                                    <div class="param-text">{{ $item['parameters'] }}</div>
                                </td>
                                <td>
                                    <div class="action-cell">
                                        @php
                                            $resolvedTestEndpoint = str_replace('{type}', 'soil', $item['endpoint']);
                                        @endphp
                                        <button
                                            type="button"
                                            class="btn-try"
                                            data-open-tester
                                            data-method="{{ $item['method'] }}"
                                            data-endpoint="{{ $resolvedTestEndpoint }}"
                                            data-title="{{ $group['title'] }}"
                                            data-sample="{{ $sampleJson }}"
                                            data-multipart="{{ $isMultipartLeaf ? '1' : '0' }}"
                                        >
                                            ⚡ Uji Endpoint
                                        </button>
                                        @if($item['method'] === 'GET')
                                            @php
                                                $openUrl = $item['endpoint'] === '/api'
                                                    ? url('/api?format=json')
                                                    : url($resolvedTestEndpoint);
                                                $isCsvExport = str_starts_with($item['endpoint'], '/api/reports/export/');
                                            @endphp
                                            <a href="{{ $openUrl }}" target="_blank" rel="noopener" class="btn-open-json">
                                                {{ $isCsvExport ? '📥 Unduh CSV ↗' : '📄 Buka JSON ↗' }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </main>

    <footer class="footer">
        🌶️ ChiliVision · Dokumentasi &amp; Playground REST API Laravel + TiDB Cloud ({{ $version }})
    </footer>
</div>

<!-- Native Dialog for Testing Endpoints Directly -->
<dialog id="apiTesterDialog" closedby="any" aria-labelledby="testerDialogTitle">
    <div class="dialog-header">
        <h2 id="testerDialogTitle">⚡ Uji Endpoint API Secara Langsung</h2>
        <form method="dialog">
            <button type="submit" class="dialog-close-btn" value="close">✕ Tutup</button>
        </form>
    </div>
    <div class="dialog-body">
        <div class="tester-bar">
            <span id="testerMethodBadge" class="method-badge method-get">GET</span>
            <input type="text" id="testerEndpointInput" class="tester-url" readonly aria-label="Endpoint URL">
            <button type="button" id="testerSendBtn" class="tester-send-btn">🚀 Kirim Request</button>
        </div>

        <div id="testerMultipartSection" hidden>
            <label class="tester-field-label" for="testerFileInput">📷 Pilih File Gambar Daun (<code>file</code>) untuk <code>POST /api/leaf-image</code>:</label>
            <input type="file" id="testerFileInput" accept="image/jpeg,image/png,image/webp" class="tester-url">
        </div>

        <div id="testerJsonSection" hidden>
            <label class="tester-field-label" for="testerPayloadInput">📦 Request Body (JSON):</label>
            <textarea id="testerPayloadInput" class="tester-textarea" spellcheck="false"></textarea>
        </div>

        <div class="tester-meta">
            <span id="testerStatusLabel">Klik "Kirim Request" untuk mengeksekusi endpoint.</span>
            <span id="testerTimeLabel"></span>
        </div>

        <pre role="region" aria-labelledby="testerDialogTitle" tabindex="0" class="response-pre"><code id="testerResponseCode">// Respons JSON dari server akan tampil di sini...</code></pre>
    </div>
</dialog>

<script>
    (function () {
        const searchInput = document.getElementById('apiSearchInput');
        const filterBtns = document.querySelectorAll('[data-method-filter]');
        const rows = document.querySelectorAll('[data-api-row]');
        const groups = document.querySelectorAll('[data-group-section]');

        let activeMethod = 'ALL';

        function applyFilter() {
            const query = (searchInput.value || '').trim().toLowerCase();

            rows.forEach((row) => {
                const rowMethod = row.getAttribute('data-method');
                const rowSearch = row.getAttribute('data-search') || '';
                const matchMethod = (activeMethod === 'ALL' || rowMethod === activeMethod);
                const matchSearch = (!query || rowSearch.includes(query));

                row.hidden = !(matchMethod && matchSearch);
            });

            groups.forEach((group) => {
                const visibleRows = group.querySelectorAll('[data-api-row]:not([hidden])');
                group.hidden = (visibleRows.length === 0);
            });
        }

        searchInput.addEventListener('input', applyFilter);

        filterBtns.forEach((btn) => {
            btn.addEventListener('click', () => {
                filterBtns.forEach((b) => b.classList.remove('active'));
                btn.classList.add('active');
                activeMethod = btn.getAttribute('data-method-filter') || 'ALL';
                applyFilter();
            });
        });

        // Interactive API Tester Dialog
        const dialog = document.getElementById('apiTesterDialog');
        const methodBadge = document.getElementById('testerMethodBadge');
        const endpointInput = document.getElementById('testerEndpointInput');
        const sendBtn = document.getElementById('testerSendBtn');
        const jsonSection = document.getElementById('testerJsonSection');
        const multipartSection = document.getElementById('testerMultipartSection');
        const fileInput = document.getElementById('testerFileInput');
        const payloadInput = document.getElementById('testerPayloadInput');
        const statusLabel = document.getElementById('testerStatusLabel');
        const timeLabel = document.getElementById('testerTimeLabel');
        const responseCode = document.getElementById('testerResponseCode');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        let currentMethod = 'GET';
        let currentMultipart = false;

        document.querySelectorAll('[data-open-tester]').forEach((btn) => {
            btn.addEventListener('click', () => {
                currentMethod = btn.getAttribute('data-method') || 'GET';
                const endpoint = btn.getAttribute('data-endpoint') || '/api';
                const sample = btn.getAttribute('data-sample') || '';
                currentMultipart = btn.getAttribute('data-multipart') === '1';

                methodBadge.textContent = currentMethod;
                methodBadge.className = 'method-badge method-' + currentMethod.toLowerCase();
                endpointInput.value = endpoint === '/api' ? '/api?format=json' : endpoint;

                if (currentMethod === 'POST' && currentMultipart) {
                    multipartSection.hidden = false;
                    jsonSection.hidden = true;
                } else if (currentMethod === 'POST') {
                    multipartSection.hidden = true;
                    jsonSection.hidden = false;
                    payloadInput.value = sample || '{}';
                } else {
                    multipartSection.hidden = true;
                    jsonSection.hidden = true;
                }

                statusLabel.textContent = 'Siap mengeksekusi ' + currentMethod + ' ' + endpointInput.value;
                timeLabel.textContent = '';
                responseCode.textContent = '// Klik "Kirim Request" untuk melihat hasil JSON...';

                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                }
                if (currentMethod === 'GET') {
                    executeTestRequest();
                }
            });
        });

        async function executeTestRequest() {
            const targetUrl = endpointInput.value;
            sendBtn.disabled = true;
            statusLabel.textContent = '⏳ Mengirim request ke ' + targetUrl + '...';
            timeLabel.textContent = '';
            const t0 = performance.now();

            try {
                const options = {
                    method: currentMethod,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    credentials: 'same-origin'
                };

                if (currentMethod === 'POST') {
                    if (currentMultipart) {
                        const fd = new FormData();
                        if (fileInput.files && fileInput.files[0]) {
                            fd.append('file', fileInput.files[0]);
                        }
                        options.body = fd;
                    } else {
                        options.headers['Content-Type'] = 'application/json';
                        options.body = payloadInput.value || '{}';
                    }
                }

                const res = await fetch(targetUrl, options);
                const elapsed = Math.round(performance.now() - t0);
                const text = await res.text();

                statusLabel.textContent = (res.ok ? '✅ HTTP ' : '⚠️ HTTP ') + res.status + ' ' + res.statusText;
                timeLabel.textContent = elapsed + ' ms';

                try {
                    const parsed = JSON.parse(text);
                    responseCode.textContent = JSON.stringify(parsed, null, 2);
                } catch {
                    responseCode.textContent = text;
                }
            } catch (err) {
                statusLabel.textContent = '❌ Gagal menghubungi endpoint';
                responseCode.textContent = String(err);
            } finally {
                sendBtn.disabled = false;
            }
        }

        sendBtn.addEventListener('click', executeTestRequest);

        // Theme Toggle (Light / Dark Mode with Smooth View Transition & Cross-Tab Sync)
        const meta = document.querySelector('meta[name="color-scheme"]');
        const toggleBtn = document.getElementById('themeToggleBtn');
        const darkMql = window.matchMedia('(prefers-color-scheme: dark)');

        function getSystemScheme() {
            return darkMql.matches ? 'dark' : 'light';
        }

        function getCurrentRenderedScheme() {
            try {
                const stored = localStorage.getItem('color-scheme');
                if (stored === 'dark' || stored === 'light') return stored;
            } catch (e) {}
            return getSystemScheme();
        }

        function applyScheme(target) {
            const sys = getSystemScheme();
            try {
                if (target !== sys) {
                    localStorage.setItem('color-scheme', target);
                    meta.content = target;
                } else {
                    localStorage.removeItem('color-scheme');
                    meta.content = 'light dark';
                }
            } catch (e) {
                meta.content = target;
            }
        }

        if (toggleBtn && meta) {
            toggleBtn.addEventListener('click', () => {
                toggleBtn.classList.add('is-pressed');
                setTimeout(() => toggleBtn.classList.remove('is-pressed'), 140);

                const current = getCurrentRenderedScheme();
                const target = current === 'dark' ? 'light' : 'dark';
                const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                if (document.startViewTransition && !reducedMotion) {
                    document.startViewTransition(() => {
                        applyScheme(target);
                    });
                } else {
                    document.documentElement.classList.add('theme-fallback-transition');
                    applyScheme(target);
                }
            });

            window.addEventListener('storage', (e) => {
                if (e.key === 'color-scheme') {
                    meta.content = e.newValue ?? 'light dark';
                }
            });
        }
    })();
</script>
</body>
</html>
