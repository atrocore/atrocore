<div class="error-container">
    <div class="error-panel">
        <picture class="logo">
            <img src="{{logo}}" alt="">
        </picture>

        <div class="error-content">
            <h1>{{ translate 'pageNotFound' }}</h1>
            <p>{{ translate 'pageNotFoundDescription' }}</p>
            <p class="error-code">{{ translate 'errorCode' }}: 404</p>
        </div>

        <div class="error-button">
            <a role="button" class="primary" href="#">{{ translate 'Open Dashboard' }}</a>
        </div>

        <div class="error-links">
            <a href="https://github.com/atrocore/atrocore/issues/new" target="_blank">{{translate 'Report a Bug'}}</a>
            <a href="https://community.atrocore.com" target="_blank">{{translate 'Visit Community'}}</a>
            <a href="https://help.atrocore.com" target="_blank">{{translate 'Help Center'}}</a>
            <a href="https://support.atrocore.com" target="_blank">{{translate 'Support'}}</a>
            <a href="https://atrocore.com/contact" target="_blank">{{translate 'Contact Us'}}</a>
        </div>
    </div>
</div>
