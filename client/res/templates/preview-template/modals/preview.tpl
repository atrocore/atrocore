<div class="html-preview">
    <div class="header-container" style="display: flex; justify-content: center">
        <div class="screen-size-icons" style="text-align: center">
            <a href="javascript:" class="profile"
               data-action="changeProfile"
               data-profile="mobile"
               title="Iphone 12 pro: 390x844"
               style="margin-left: 5px; color:{{#if isMobile }}green{{else}}gray{{/if}};" >
                <span class="fa fa-mobile-alt fa-2x"></span>
            </a>
            <a href="javascript:"
               class="profile"
               data-action="changeProfile"
               title="IPad pro: 1024*1366"
               data-profile="tablet" style="margin-left: 5px; color:{{#if isTablet }}green{{else}}gray{{/if}};">
                <span class="fa fa-tablet-alt fa-2x"></span>
            </a>
            <a href="javascript:"
               class="profile"
               data-action="changeProfile"
               data-profile="desktop"
               title="1920x1080"
               style="margin-left: 5px; color:{{#if isDesktop }}green{{else}}gray{{/if}};">
                <span class="fa fa-desktop fa-2x"></span>
            </a>
        </div>
    </div>

    <a href="javascript:" class="close">
        <span aria-hidden="true">×</span>
    </a>
    <div class="body-container" style="display: flex; flex-direction: column; align-items: center">
            <h3>Preview</h3>
            <iframe
                    style="width:{{size.width}};height:{{size.height}}; 0.5px solid rgba(0,0,0,0.2) ">
            </iframe>
    </div>
</div>