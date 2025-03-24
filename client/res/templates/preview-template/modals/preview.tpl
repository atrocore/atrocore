<div class="html-preview">
    <div class="header-container">
        <div class="screen-size-icons" role="group">
            {{#if hasMultipleLanguages }}
                <select class="language-selector"></select>
            {{/if}}
            <button class="btn {{#if editorActive}}btn-primary{{else}}btn-default{{/if}} editor-toggle"
                    data-action="toggleEditor"><span class="fa fa-expand"></span></button>
            <div class="btn-group" role="group">
                <button class="btn profile {{#if isMobile }}btn-primary{{else}}btn-default{{/if}}"
                        data-action="changeProfile"
                        data-profile="mobile"
                        title="Iphone 12 pro">
                    <span class="fa fa-mobile-alt"></span>
                </button>
                <button class="btn profile {{#if isTablet }}btn-primary{{else}}btn-default{{/if}}"
                        data-action="changeProfile"
                        title="IPad pro"
                        data-profile="tablet">
                    <span class="fa fa-tablet-alt"></span>
                </button>
                <button class="btn profile {{#if isDesktop }}btn-primary{{else}}btn-default{{/if}}"
                        data-action="changeProfile"
                        data-profile="desktop"
                        title="Desktop">
                    <span class="fa fa-desktop"></span>
                </button>
            </div>
        </div>
    </div>

    <a href="javascript:" class="close" data-action="close-modal">
        <span aria-hidden="true">×</span>
    </a>
    <div class="body-container">
        <div class="main-container">
            <iframe style="width:{{size.width}};height:{{size.height}};"></iframe>
        </div>
        <div class="side-container">
            {{{sideEdit}}}
        </div>
    </div>
</div>