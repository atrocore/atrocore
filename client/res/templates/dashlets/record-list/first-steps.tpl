<div class="cards-container first-steps-dashlet">
    {{#each items}}
        <a href="{{ url }}" class="card{{#if completed}} completed{{/if}}"{{#if urlNewTab}} target="_blank"{{/if}} data-step="{{ name }}"><i class="{{#if completed}}ph-fill ph-check-fat{{ else }}{{ icon }}{{/if}} icon"></i><span class="title">{{ title }}</span>{{#if description}}<span class="description">{{description}}</span>{{/if}}</a>
    {{/each}}
</div>