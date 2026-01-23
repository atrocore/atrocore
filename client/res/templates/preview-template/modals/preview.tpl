<div class="html-preview">
    <div class="header-container">
        {{#if title}}
            <div class="header-breadcrumbs">
                <span>{{ title }}</span>
            </div>
        {{/if}}
        <div class="screen-size-icons" role="group">
            <div class="language-container"></div>
            <button class="btn {{#if editorActive}}btn-primary{{else}}btn-default{{/if}} editor-toggle"
                    data-action="toggleEditor"><i class="ph ph-corners-out"></i></button>
            <div class="btn-group" role="group">
                <button class="btn profile {{#if isMobile }}btn-primary{{else}}btn-default{{/if}}"
                        data-action="changeProfile"
                        data-profile="mobile"
                        title="Mobile">
                    <i class="ph ph-device-mobile"></i>
                </button>
                <button class="btn profile {{#if isTablet }}btn-primary{{else}}btn-default{{/if}}"
                        data-action="changeProfile"
                        title="Tablet"
                        data-profile="tablet">
                    <i class="ph ph-device-tablet"></i>
                </button>
                <button class="btn profile {{#if isDesktop }}btn-primary{{else}}btn-default{{/if}}"
                        data-action="changeProfile"
                        data-profile="desktop"
                        title="Desktop">
                    <i class="ph ph-desktop"></i>
                </button>
            </div>
        </div>
    </div>

    <a href="javascript:" class="close" data-action="close-modal">
        <span aria-hidden="true"><i class="ph ph-x"></i></span>
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