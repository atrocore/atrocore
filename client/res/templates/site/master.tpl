<div id="title-bar" class="custom-controls">
    <div class="title-container">
        <div class="icons-container">
            <img src="{{faviconUrl}}">
            <a href="#" data-action="openWindow" title="{{ translate 'openWindow' }}">
                <i class="ph ph-arrow-square-out"></i>
            </a>
        </div>
        <div class="title"></div>
    </div>
    <div class="buttons-container">
        <button data-action="copyUrl" title="{{ translate 'copyUrl' }}"><i class="ph ph-copy"></i></button>
        <button data-action="goBack" title="{{ translate 'goBack' }}"><i class="ph ph-arrow-left"></i></button>
        <button data-action="goForward" title="{{ translate 'goForward' }}"><i class="ph ph-arrow-right"></i></button>
        <button data-action="reload" title="{{ translate 'reloadPage' }}"><i class="ph ph-arrow-clockwise"></i></button>
        <button data-action="splitHorizontal" title="{{ translate 'splitHorizontal' }}"><i class="ph ph-square-split-horizontal"></i></button>
        <button data-action="splitVertical" title="{{ translate 'splitVertical' }}"><i class="ph ph-square-split-vertical"></i></button>
        <button data-action="expand" title="{{ translate 'expandWindow' }}"><i class="ph ph-arrows-out"></i></button>
    </div>
</div>
<header id="header">{{{header}}}</header>
<div id="content" class="container content">
    <div id="main">{{{main}}}</div>
</div>