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
        :root {
            font-family: 'Plus Jakarta Sans', Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --in-oklab: ;

            --bg-light: #ecf5ee;
            --bg-dark: #06100b;
            --bg-grad-start-light: #f3fbf6;
            --bg-grad-mid-light: #e2f2e8;
            --bg-grad-end-light: #d2eade;
            --bg-grad-start-dark: #050d09;
            --bg-grad-mid-dark: #081710;
            --bg-grad-end-dark: #0b2017;

            --surface-light: rgba(255, 255, 255, 0.68);
            --surface-dark: rgba(16, 33, 24, 0.62);
            --surface-alt-light: rgba(255, 255, 255, 0.46);
            --surface-alt-dark: rgba(22, 44, 32, 0.5);
            --topbar-bg-light: rgba(255, 255, 255, 0.6);
            --topbar-bg-dark: rgba(13, 29, 21, 0.62);

            --glass-bg-top-light: rgba(255, 255, 255, 0.78);
            --glass-bg-bottom-light: rgba(244, 252, 247, 0.5);
            --glass-bg-top-dark: rgba(24, 49, 36, 0.66);
            --glass-bg-bottom-dark: rgba(12, 26, 19, 0.52);

            --glass-border-light: rgba(255, 255, 255, 0.74);
            --glass-border-dark: rgba(167, 243, 208, 0.16);
            --glass-highlight-light: rgba(255, 255, 255, 0.94);
            --glass-highlight-dark: rgba(236, 253, 245, 0.22);
            --glass-edge-left-light: rgba(255, 255, 255, 0.65);
            --glass-edge-left-dark: rgba(236, 253, 245, 0.1);
            --glass-inner-shade-light: rgba(16, 62, 38, 0.06);
            --glass-inner-shade-dark: rgba(0, 0, 0, 0.32);

            --glass-shadow-light: rgba(12, 48, 30, 0.11);
            --glass-shadow-sm-light: rgba(12, 48, 30, 0.05);
            --glass-shadow-dark: rgba(0, 0, 0, 0.48);
            --glass-shadow-sm-dark: rgba(0, 0, 0, 0.28);

            --orb-1-light: rgba(34, 197, 94, 0.24);
            --orb-2-light: rgba(14, 165, 233, 0.2);
            --orb-3-light: rgba(16, 185, 129, 0.18);
            --orb-1-dark: rgba(34, 197, 94, 0.19);
            --orb-2-dark: rgba(14, 165, 233, 0.16);
            --orb-3-dark: rgba(139, 92, 246, 0.12);

            --text-main-light: #13221a;
            --text-main-dark: #e8f5ec;
            --dark-light: #0b2418;
            --dark-dark: #f2fdf5;
            --text-muted-light: #4b6354;
            --text-muted-dark: #9db9a6;

            --green-light: #146c43;
            --green-dark: #4ade80;
            --green-hover-light: #0e5332;
            --green-hover-dark: #22c55e;
            --mint-light: rgba(22, 163, 74, 0.13);
            --mint-dark: rgba(74, 222, 128, 0.15);
            --line-light: rgba(20, 83, 45, 0.14);
            --line-dark: rgba(167, 243, 208, 0.14);

            --blue-light: #0284c7;
            --blue-dark: #38bdf8;
            --blue-hover-light: #0369a1;
            --blue-hover-dark: #7dd3fc;
            --blue-bg-light: rgba(14, 165, 233, 0.14);
            --blue-bg-dark: rgba(56, 189, 248, 0.16);

            --code-bg-light: rgba(20, 108, 67, 0.1);
            --code-bg-dark: rgba(52, 211, 153, 0.14);
            --code-text-light: #115130;
            --code-text-dark: #86efac;

            --focus-ring-light: rgba(20, 108, 67, 0.28);
            --focus-ring-dark: rgba(74, 222, 128, 0.35);

            --color-scheme: light;
            --bg: var(--bg-light);
            --bg-grad-start: var(--bg-grad-start-light);
            --bg-grad-mid: var(--bg-grad-mid-light);
            --bg-grad-end: var(--bg-grad-end-light);
            --surface: var(--surface-light);
            --surface-alt: var(--surface-alt-light);
            --topbar-bg: var(--topbar-bg-light);
            --glass-bg-top: var(--glass-bg-top-light);
            --glass-bg-bottom: var(--glass-bg-bottom-light);
            --glass-border: var(--glass-border-light);
            --glass-highlight: var(--glass-highlight-light);
            --glass-edge-left: var(--glass-edge-left-light);
            --glass-inner-shade: var(--glass-inner-shade-light);
            --glass-shadow: var(--glass-shadow-light);
            --glass-shadow-sm: var(--glass-shadow-sm-light);
            --orb-1: var(--orb-1-light);
            --orb-2: var(--orb-2-light);
            --orb-3: var(--orb-3-light);
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
            --focus-ring: var(--focus-ring-light);

            @media (prefers-color-scheme: dark) {
                --color-scheme: dark;
                --bg: var(--bg-dark);
                --bg-grad-start: var(--bg-grad-start-dark);
                --bg-grad-mid: var(--bg-grad-mid-dark);
                --bg-grad-end: var(--bg-grad-end-dark);
                --surface: var(--surface-dark);
                --surface-alt: var(--surface-alt-dark);
                --topbar-bg: var(--topbar-bg-dark);
                --glass-bg-top: var(--glass-bg-top-dark);
                --glass-bg-bottom: var(--glass-bg-bottom-dark);
                --glass-border: var(--glass-border-dark);
                --glass-highlight: var(--glass-highlight-dark);
                --glass-edge-left: var(--glass-edge-left-dark);
                --glass-inner-shade: var(--glass-inner-shade-dark);
                --glass-shadow: var(--glass-shadow-dark);
                --glass-shadow-sm: var(--glass-shadow-sm-dark);
                --orb-1: var(--orb-1-dark);
                --orb-2: var(--orb-2-dark);
                --orb-3: var(--orb-3-dark);
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
                --focus-ring: var(--focus-ring-dark);
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
                --glass-bg-top: var(--glass-bg-top-dark);
                --glass-bg-bottom: var(--glass-bg-bottom-dark);
                --glass-border: var(--glass-border-dark);
                --glass-highlight: var(--glass-highlight-dark);
                --glass-edge-left: var(--glass-edge-left-dark);
                --glass-inner-shade: var(--glass-inner-shade-dark);
                --glass-shadow: var(--glass-shadow-dark);
                --glass-shadow-sm: var(--glass-shadow-sm-dark);
                --orb-1: var(--orb-1-dark);
                --orb-2: var(--orb-2-dark);
                --orb-3: var(--orb-3-dark);
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
                --focus-ring: var(--focus-ring-dark);
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
                --glass-bg-top: var(--glass-bg-top-light);
                --glass-bg-bottom: var(--glass-bg-bottom-light);
                --glass-border: var(--glass-border-light);
                --glass-highlight: var(--glass-highlight-light);
                --glass-edge-left: var(--glass-edge-left-light);
                --glass-inner-shade: var(--glass-inner-shade-light);
                --glass-shadow: var(--glass-shadow-light);
                --glass-shadow-sm: var(--glass-shadow-sm-light);
                --orb-1: var(--orb-1-light);
                --orb-2: var(--orb-2-light);
                --orb-3: var(--orb-3-light);
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
                --focus-ring: var(--focus-ring-light);
            }

            color-scheme: light dark;
            accent-color: var(--green);
            color: var(--text-main);
            background: var(--bg);
            --amber: #b45309;
            --amber-bg: rgba(245, 158, 11, 0.16);
            --purple: #7c3aed;
            --purple-hover: #6d28d9;
            --purple-bg: rgba(124, 58, 237, 0.14);
        }
        @supports (background: linear-gradient(in oklab, white, black)) {
            :root {
                --in-oklab: in oklab;
            }
        }
        * { box-sizing: border-box; }
        ::view-transition-group(root) {
            animation-duration: 0.24s;
            animation-timing-function: cubic-bezier(0.22, 1, 0.36, 1);
        }
        ::view-transition-old(root) {
            animation: none;
            z-index: 1;
            mix-blend-mode: normal;
        }
        ::view-transition-new(root) {
            animation: themeSmoothFadeIn 0.24s cubic-bezier(0.22, 1, 0.36, 1) both;
            z-index: 2;
            mix-blend-mode: normal;
        }
        @keyframes themeSmoothFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        :root.theme-switching,
        :root.theme-switching *,
        :root.theme-switching *::before,
        :root.theme-switching *::after {
            transition: none !important;
        }
        body {
            margin: 0;
            background:
                radial-gradient(ellipse 75% 45% at 10% 0%, var(--orb-1), transparent 62%),
                radial-gradient(ellipse 65% 40% at 92% 6%, var(--orb-2), transparent 60%),
                radial-gradient(ellipse 70% 45% at 80% 55%, var(--orb-3), transparent 62%),
                radial-gradient(ellipse 65% 45% at 18% 92%, var(--orb-1), transparent 60%),
                linear-gradient(140deg var(--in-oklab), var(--bg-grad-start) 0%, var(--bg-grad-mid) 52%, var(--bg-grad-end) 100%);
            color: var(--text-main);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        :where(a, button, input, select, textarea, summary, [tabindex]):focus-visible {
            outline: 2px solid var(--green);
            outline-offset: 2px;
        }
        .shell {
            max-width: 1380px;
            margin: 0 auto;
            padding: 22px 22px 68px;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 28px;
            background: linear-gradient(160deg var(--in-oklab), var(--glass-bg-top) 0%, var(--topbar-bg) 100%);
            backdrop-filter: blur(14px) saturate(150%);
            -webkit-backdrop-filter: blur(14px) saturate(150%);
            padding: 13px 20px;
            border-radius: 22px;
            corner-shape: squircle;
            border: 1px solid var(--glass-border);
            box-shadow:
                0 14px 32px -12px var(--glass-shadow),
                0 3px 10px -2px var(--glass-shadow-sm),
                inset 0 1px 0 0 var(--glass-highlight),
                inset 0 -1px 0 0 var(--glass-inner-shade);
        }
        .topbar-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            width: 100%;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 13px;
            font-weight: 800;
            letter-spacing: -.028em;
            color: var(--dark);
            font-size: 1.08rem;
            text-decoration: none;
            min-width: 0;
        }
        .polije-brand-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 13px;
            border-radius: 14px;
            background: var(--surface-alt);
            border: 1px solid var(--line);
            box-shadow: inset 0 1px 0 var(--glass-highlight), 0 4px 12px -4px var(--glass-shadow-sm);
            flex-shrink: 0;
            max-width: 100%;
        }
        .footer .polije-brand-pill {
            background: transparent;
            border-color: transparent;
            box-shadow: none;
            padding: 2px 4px;
        }
        .polije-logo {
            height: 34px;
            width: auto;
            max-width: 100%;
            display: block;
            object-fit: contain;
            filter: drop-shadow(0 1px 4px rgba(0, 0, 0, 0.12));
        }
        .brand-mark {
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            border-radius: 14px;
            corner-shape: squircle;
            background: linear-gradient(140deg, #1d8a53 0%, #125c37 100%);
            color: #eaffeb;
            font-size: 20px;
            box-shadow:
                0 8px 20px -4px rgba(20, 108, 67, 0.38),
                inset 0 1px 0 rgba(255, 255, 255, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.35);
            flex-shrink: 0;
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
            gap: 8px;
            flex-wrap: wrap;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: .76rem;
            font-weight: 700;
            padding: 7px 14px;
            border-radius: 999px;
            background: var(--mint);
            color: var(--green);
            text-decoration: none;
            border: 1px solid color-mix(in oklab, var(--green) 24%, var(--glass-border));
            box-shadow: inset 0 1px 0 var(--glass-highlight), 0 2px 8px -2px var(--glass-shadow-sm);
            transition: transform .18s cubic-bezier(0.22, 1, 0.36, 1), box-shadow .18s ease, background-color .18s ease;
        }
        a.badge:hover {
            transform: translateY(-2px);
            box-shadow: inset 0 1px 0 var(--glass-highlight), 0 8px 18px -6px var(--glass-shadow);
        }
        .badge.primary-action {
            background: linear-gradient(180deg, #1f935a 0%, #146c43 100%);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.25);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.38), 0 6px 16px -6px rgba(20, 108, 67, 0.45);
        }
        .badge.mysql {
            background: var(--blue-bg);
            color: var(--blue-hover);
            border-color: color-mix(in oklab, var(--blue) 28%, var(--glass-border));
        }
        .badge.laravel {
            background: rgba(239, 68, 68, 0.13);
            color: #ef4444;
            border-color: rgba(239, 68, 68, 0.28);
        }
        button.theme-toggle-btn {
            appearance: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 999px;
            font: inherit;
            font-size: .76rem;
            font-weight: 700;
            background: linear-gradient(180deg var(--in-oklab), var(--glass-bg-top), var(--surface-alt));
            color: var(--dark);
            border: 1px solid var(--line);
            cursor: pointer;
            user-select: none;
            box-shadow: inset 0 1px 0 var(--glass-highlight), 0 4px 12px -4px var(--glass-shadow);
            transition: transform 0.1s ease, box-shadow 0.1s ease, background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }
        button.theme-toggle-btn:hover {
            background: var(--mint);
            color: var(--green);
            border-color: color-mix(in oklab, var(--green) 42%, var(--glass-border));
            transform: none;
        }
        button.theme-toggle-btn:active,
        button.theme-toggle-btn.is-pressed {
            transform: scale(0.94) translateY(1px);
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.16);
        }
        .if-dark { display: none; }
        @media (prefers-color-scheme: dark) {
            :root:not(:has(> head > meta[name="color-scheme"][content="light"])) .if-light { display: none !important; }
            :root:not(:has(> head > meta[name="color-scheme"][content="light"])) .if-dark { display: inline-flex !important; }
        }
        :root:has(> head > meta[name="color-scheme"][content="dark"]) .if-light { display: none !important; }
        :root:has(> head > meta[name="color-scheme"][content="dark"]) .if-dark { display: inline-flex !important; }

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
            font-size: .73rem;
            font-weight: 800;
            letter-spacing: .11em;
            text-transform: uppercase;
            padding: 6px 14px;
            border-radius: 999px;
            background: linear-gradient(180deg var(--in-oklab), var(--glass-bg-top), var(--surface-alt));
            border: 1px solid var(--glass-border);
            box-shadow: inset 0 1px 0 var(--glass-highlight), 0 4px 12px -4px var(--glass-shadow-sm);
        }
        .eyebrow::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 10px var(--green);
        }
        .hero h1 {
            font-size: clamp(1.7rem, 2.9vw, 2.45rem);
            line-height: 1.15;
            letter-spacing: -.038em;
            margin: 12px 0 6px;
            color: var(--dark);
            font-weight: 800;
        }
        .hero-desc {
            margin: 0;
            color: var(--text-muted);
            font-size: .92rem;
            line-height: 1.58;
            max-width: 780px;
        }

        /* Summary KPI Row — Liquid Glass */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }
        .kpi-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(155deg var(--in-oklab), var(--glass-bg-top) 0%, var(--glass-bg-bottom) 100%);
            backdrop-filter: blur(14px) saturate(150%);
            -webkit-backdrop-filter: blur(14px) saturate(150%);
            border: 1px solid var(--glass-border);
            border-radius: 22px;
            corner-shape: squircle;
            padding: 18px 20px;
            box-shadow:
                0 14px 32px -14px var(--glass-shadow),
                0 3px 10px -2px var(--glass-shadow-sm),
                inset 0 1px 0 0 var(--glass-highlight),
                inset 1px 0 0 0 var(--glass-edge-left),
                inset 0 -1px 0 0 var(--glass-inner-shade);
            transition: transform .22s cubic-bezier(0.22, 1, 0.36, 1), box-shadow .22s ease;
        }
        .kpi-card::before {
            content: "";
            position: absolute;
            inset: 0 18px auto 18px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--glass-highlight), transparent);
            pointer-events: none;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow:
                0 20px 40px -14px var(--glass-shadow),
                inset 0 1px 0 0 var(--glass-highlight);
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
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 4px;
            font-variant-numeric: tabular-nums;
        }
        .kpi-sub {
            font-size: .78rem;
            color: var(--text-muted);
            line-height: 1.45;
        }

        /* Search & Filter Bar — Liquid Glass */
        .filter-card {
            position: relative;
            background: linear-gradient(155deg var(--in-oklab), var(--glass-bg-top) 0%, var(--glass-bg-bottom) 100%);
            backdrop-filter: blur(14px) saturate(150%);
            -webkit-backdrop-filter: blur(14px) saturate(150%);
            border: 1px solid var(--glass-border);
            border-radius: 22px;
            corner-shape: squircle;
            padding: 18px 20px;
            margin-bottom: 22px;
            box-shadow:
                0 14px 34px -14px var(--glass-shadow),
                inset 0 1px 0 0 var(--glass-highlight),
                inset 0 -1px 0 0 var(--glass-inner-shade);
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
            border-radius: 13px;
            border: 1px solid var(--line);
            padding: 10px 14px;
            font: inherit;
            font-size: .86rem;
            color: var(--text-main);
            background: var(--surface-alt);
            box-shadow: inset 0 1px 2px var(--glass-inner-shade);
        }
        .search-input:focus {
            outline: none;
            border-color: var(--green);
            background: var(--surface);
            box-shadow: 0 0 0 3.5px var(--focus-ring), inset 0 1px 0 var(--glass-highlight);
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
            border-radius: 999px;
            cursor: pointer;
            box-shadow: inset 0 1px 0 var(--glass-highlight);
            transition: all .16s ease;
        }
        .filter-btn:hover {
            background: var(--mint);
            border-color: color-mix(in oklab, var(--green) 40%, var(--line));
            color: var(--green);
        }
        .filter-btn.active {
            background: linear-gradient(180deg, #1f935a 0%, #146c43 100%);
            border-color: rgba(255, 255, 255, 0.24);
            color: #ffffff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.36), 0 6px 16px -6px rgba(20, 108, 67, 0.45);
        }
        .group-nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding-top: 10px;
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
            border-radius: 999px;
            box-shadow: inset 0 1px 0 var(--glass-highlight);
            transition: all .16s ease;
        }
        .group-chip:hover {
            background: var(--mint);
            color: var(--green);
            border-color: color-mix(in oklab, var(--green) 40%, var(--glass-border));
            transform: translateY(-1px);
        }

        /* Group Cards & Responsive Tables — Liquid Glass */
        .api-group-card {
            position: relative;
            background: linear-gradient(155deg var(--in-oklab), var(--glass-bg-top) 0%, var(--glass-bg-bottom) 100%);
            backdrop-filter: blur(14px) saturate(150%);
            -webkit-backdrop-filter: blur(14px) saturate(150%);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            corner-shape: squircle;
            padding: 23px;
            margin-bottom: 22px;
            box-shadow:
                0 18px 40px -16px var(--glass-shadow),
                0 4px 12px -2px var(--glass-shadow-sm),
                inset 0 1px 0 0 var(--glass-highlight),
                inset 1px 0 0 0 var(--glass-edge-left),
                inset 0 -1px 0 0 var(--glass-inner-shade);
            scroll-margin-top: 24px;
        }
        .api-group-card::before {
            content: "";
            position: absolute;
            inset: 0 24px auto 24px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--glass-highlight), transparent);
            pointer-events: none;
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
            padding: 5px 12px;
            border-radius: 999px;
            background: var(--mint);
            color: var(--green);
            border: 1px solid color-mix(in oklab, var(--green) 24%, var(--glass-border));
            box-shadow: inset 0 1px 0 var(--glass-highlight);
        }
        .group-note {
            margin: 0 0 16px;
            padding: 11px 15px;
            border-radius: 14px;
            background: var(--surface-alt);
            border: 1px solid var(--glass-border);
            border-left: 4px solid var(--green);
            color: var(--text-main);
            font-size: .82rem;
            line-height: 1.5;
            box-shadow: inset 0 1px 0 var(--glass-highlight);
        }

        .table-wrapper {
            overflow: auto;
            max-inline-size: 100%;
            container-type: inline-size;
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            background: var(--surface-alt);
            box-shadow: inset 0 1px 0 var(--glass-highlight);
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
            background: var(--surface);
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
        table.api-spec-table tbody tr {
            transition: background-color .15s ease;
        }
        table.api-spec-table tbody tr:hover {
            background: var(--surface);
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
            border-radius: 8px;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: .74rem;
            font-weight: 700;
            letter-spacing: .03em;
            box-shadow: inset 0 1px 0 var(--glass-highlight);
        }
        .method-badge.method-get {
            background: var(--mint);
            color: var(--green);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        .method-badge.method-post {
            background: var(--blue-bg);
            color: var(--blue-hover);
            border: 1px solid rgba(56, 189, 248, 0.32);
        }

        code {
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            background: var(--code-bg);
            padding: 2px 7px;
            border-radius: 6px;
            font-size: .77rem;
            line-height: 1.35;
            color: var(--code-text);
            font-weight: 600;
            border: 1px solid color-mix(in oklab, var(--green) 16%, transparent);
            box-decoration-break: clone;
            -webkit-box-decoration-break: clone;
        }
        .endpoint-primary {
            font-size: .83rem;
            background: var(--code-bg);
            color: var(--code-text);
            padding: 4px 8px;
            display: inline-block;
            margin-bottom: 4px;
            white-space: nowrap;
        }
        .alias-list {
            margin-top: 5px;
            font-size: .75rem;
            color: var(--text-muted);
            line-height: 1.75;
        }
        .alias-list code {
            display: inline-block;
            white-space: nowrap;
            font-size: .72rem;
            line-height: 1.35;
            margin: 2px 2px 2px 0;
            background: var(--surface);
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
            border-radius: 7px;
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
            border: 1px solid color-mix(in oklab, var(--green) 28%, var(--glass-border));
            background: var(--mint);
            color: var(--green);
            font: inherit;
            font-size: .75rem;
            font-weight: 700;
            padding: 7px 11px;
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            box-shadow: inset 0 1px 0 var(--glass-highlight);
            transition: all .16s ease;
        }
        .btn-try:hover {
            background: linear-gradient(180deg, #1f935a 0%, #146c43 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 14px -4px rgba(20, 108, 67, 0.4);
        }
        .btn-open-json {
            font-size: .73rem;
            font-weight: 700;
            color: var(--blue-hover);
            background: var(--blue-bg);
            border: 1px solid color-mix(in oklab, var(--blue) 28%, var(--glass-border));
            padding: 6px 10px;
            border-radius: 10px;
            text-align: center;
            text-decoration: none;
            box-shadow: inset 0 1px 0 var(--glass-highlight);
            transition: all .16s ease;
        }
        .btn-open-json:hover {
            background: linear-gradient(180deg, #1bb1f5 0%, #0284c7 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 14px -4px rgba(2, 132, 199, 0.4);
        }

        /* Native Dialog Modal for Interactive API Playground — Liquid Glass */
        dialog#apiTesterDialog {
            width: min(760px, 94vw);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            corner-shape: squircle;
            padding: 0;
            background: linear-gradient(155deg var(--in-oklab), var(--glass-bg-top), var(--surface));
            backdrop-filter: blur(28px) saturate(195%);
            -webkit-backdrop-filter: blur(28px) saturate(195%);
            color: var(--dark);
            box-shadow:
                0 28px 68px -12px rgba(0, 0, 0, 0.38),
                inset 0 1px 0 var(--glass-highlight);
            overflow: hidden;
        }
        dialog#apiTesterDialog::backdrop {
            background: rgba(6, 16, 11, 0.56);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
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
            border: 1px solid var(--glass-border);
            background: var(--surface);
            color: var(--dark);
            font: inherit;
            font-size: .8rem;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: inset 0 1px 0 var(--glass-highlight);
            transition: all .15s ease;
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
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            background: var(--surface-alt);
            color: var(--dark);
        }
        .tester-send-btn {
            appearance: none;
            border: 1px solid rgba(255, 255, 255, 0.24);
            background: linear-gradient(180deg, #1f935a 0%, #146c43 100%);
            color: #ffffff;
            font: inherit;
            font-size: .84rem;
            font-weight: 700;
            padding: 10px 18px;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.36), 0 8px 18px -6px rgba(20, 108, 67, 0.45);
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .tester-send-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.42), 0 10px 22px -6px rgba(20, 108, 67, 0.55);
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
            border-radius: 12px;
            border: 1px solid var(--glass-border);
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
            border-radius: 14px;
            background: rgba(11, 29, 20, 0.92);
            color: #d9fbe5;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: .78rem;
            line-height: 1.55;
            max-height: 320px;
            overflow: auto;
            border: 1px solid rgba(167, 243, 208, 0.18);
        }
        pre.response-pre code {
            background: transparent;
            color: inherit;
            padding: 0;
            font-size: inherit;
            font-weight: 400;
            border: 0;
        }

        .footer {
            color: var(--text-muted);
            text-align: center;
            font-size: .82rem;
            margin-top: 36px;
            padding-top: 22px;
            border-top: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .footer-logo-row {
            margin-bottom: 2px;
        }
        .footer-polije-logo {
            height: 36px;
        }
        .footer-title {
            margin: 0;
            color: var(--dark);
            font-size: .86rem;
            font-weight: 700;
        }
        .footer-credit {
            margin: 0;
            color: var(--text-muted);
            font-size: .78rem;
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
            <div class="topbar-head">
                <a href="{{ url('/') }}" class="brand">
                    <span class="brand-mark" aria-hidden="true" style="padding:0;overflow:hidden;"><img src="{{ asset('images/chilivision-logo.svg') }}" alt="Logo ChiliVision" style="width:100%;height:100%;object-fit:cover;display:block;"></span>
                    <div>
                        <div>Chili Vision · REST API Documentation</div>
                        <div class="brand-sub">{{ $framework }} · Database TiDB Cloud ({{ $database }})</div>
                    </div>
                </a>
                <div class="polije-brand-pill" title="Politeknik Negeri Jember">
                    <span class="if-light"><img src="{{ asset('images/logo-polije.png') }}" alt="Logo Politeknik Negeri Jember" class="polije-logo"></span>
                    <span class="if-dark"><img src="{{ asset('images/logo-polije-dark.png') }}" alt="Logo Politeknik Negeri Jember" class="polije-logo"></span>
                </div>
            </div>
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
                <span class="eyebrow">API Explorer · Live Playground</span>
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
        <div class="footer-logo-row">
            <div class="polije-brand-pill" title="Politeknik Negeri Jember">
                <span class="if-light"><img src="{{ asset('images/logo-polije.png') }}" alt="Logo Politeknik Negeri Jember" class="polije-logo footer-polije-logo"></span>
                <span class="if-dark"><img src="{{ asset('images/logo-polije-dark.png') }}" alt="Logo Politeknik Negeri Jember" class="polije-logo footer-polije-logo"></span>
            </div>
        </div>
        <p class="footer-title">🌶️ ChiliVision · Deteksi &amp; Klasifikasi Penyakit Pada Daun Cabai</p>
        <p class="footer-credit">Dibuat oleh Kelompok 1 Golongan F Teknik Informatika</p>
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
        const root = document.documentElement;
        const meta = document.querySelector('meta[name="color-scheme"]');
        const toggleBtn = document.getElementById('themeToggleBtn');
        const darkMql = window.matchMedia('(prefers-color-scheme: dark)');
        let activeTransition = null;

        // Preload alternate theme logo so first toggle does not wait for image decode
        document.querySelectorAll('.if-light img, .if-dark img').forEach((img) => {
            const preload = new Image();
            preload.src = img.currentSrc || img.src;
            if (typeof preload.decode === 'function') {
                preload.decode().catch(() => {});
            }
        });

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

        function applySchemeWithoutElementTransitions(target) {
            root.classList.add('theme-switching');
            applyScheme(target);
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    root.classList.remove('theme-switching');
                });
            });
        }

        if (toggleBtn && meta) {
            toggleBtn.addEventListener('click', () => {
                const current = getCurrentRenderedScheme();
                const target = current === 'dark' ? 'light' : 'dark';
                const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                if (document.startViewTransition && !reducedMotion) {
                    if (activeTransition && typeof activeTransition.skipTransition === 'function') {
                        activeTransition.skipTransition();
                    }

                    root.classList.add('theme-switching');
                    const transition = document.startViewTransition(() => {
                        applyScheme(target);
                    });
                    activeTransition = transition;

                    transition.finished.finally(() => {
                        if (activeTransition === transition) {
                            activeTransition = null;
                            root.classList.remove('theme-switching');
                        }
                    });
                } else {
                    applySchemeWithoutElementTransitions(target);
                }
            });

            window.addEventListener('storage', (e) => {
                if (e.key === 'color-scheme') {
                    root.classList.add('theme-switching');
                    meta.content = e.newValue ?? 'light dark';
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            root.classList.remove('theme-switching');
                        });
                    });
                }
            });
        }
    })();
</script>
</body>
</html>
