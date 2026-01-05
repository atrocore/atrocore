<div class="cards-container">
    {{#each entities}}
        <a href="#{{ name }}" class="card entity-card"><img src="{{ icon }}" alt="" class="icon"><span class="title">{{ translate name category='scopeNamesPlural' }}</span></a>
    {{/each}}
</div>