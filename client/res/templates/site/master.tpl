<div id="title-bar" class="custom-controls">
    <button><i class="ph ph-copy"></i></button>
</div>
<header id="header">{{{header}}}</header>
<div id="content" class="container content">
    <div id="main">{{{main}}}</div>
</div>
<style>
    #title-bar {
        display: none;
        app-region: drag;
        text-align: right;
        background-color: var(--toolbar-background-color);
        color: var(--toolbar-font-color);
    }

    #title-bar button {
        app-region: no-drag;
        border: 0;
        height: 100%;
        color: inherit;
    }

    @media (display-mode: window-controls-overlay) {
        #title-bar {
            display: block;
            height: env(titlebar-area-height, 50px);
            width: env(titlebar-area-width, 100%);
        }
    }
</style>