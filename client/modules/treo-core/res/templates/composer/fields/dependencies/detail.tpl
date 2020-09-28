<table class="table">
    <thead>
    <tr>
        <th>{{translate 'version' scope='Composer' category='labels'}}</th>
        <th>{{translate 'dependencies' scope='Composer' category='labels'}}</th>
    </tr>
    </thead>
    <tbody>
    {{#each value}}
    <tr>
        <td>{{version}}</td>
        <td>
            {{#each require}}
            <div>{{@key}}: {{this}}</div>
            {{/each}}
        </td>
    </tr>
    {{/each}}
    </tbody>
</table>