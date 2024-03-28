<div class="plate-item">
	{{#if type}}
	<div class="field-type">{{type}}</div>
	{{/if}}
	<div class="field-private">
		<i class="fa {{#if private}}fa-lock{{else}}fa-lock-open{{/if}}"></i>
	</div>
	<div class="actions">{{{rowActions}}}</div>
	<div class="field-preview">
		{{#if hasIcon}}
		<span class="fiv-cla fiv-icon-{{extension}} fiv-size-lg"></span>
		{{else}}
		<img src="{{thumbnailPath}}" alt="">
		{{/if}}
	</div>
	<div class="field-name">
		<span class="record-checkbox-container">
			<input type="checkbox" class="record-checkbox" data-id="{{model.id}}">
		</span>
		<a href="#{{model.name}}/view/{{model.id}}" class="link" data-id="{{model.id}}" title="{{model.attributes.name}}">{{model.attributes.name}}</a>
	</div>
</div>

<style>
	.plate-item {
		width: 100%;
		height: 200px;
		border: 1px solid #e8eced;
		position: relative;
	}
	.plate-item.active {
		background-color: #e8eced
	}
	.plate-item .field-type {
		display: inline-block;
		position: absolute;
		top: 10px;
		left: 10px;
		border: 1px solid #e8eced;
		border-radius: 10px;
		padding: 1px 5px;
	}
	.plate-item .field-private {
		color: #999999;
		display: inline-block;
		position: absolute;
		top: 10px;
		right: 27px;
	}
	.plate-item .field-name {
		display: block;
		width: 100%;
		position: absolute;
		bottom: 10px;
		left: 0;
		padding: 0 10px 0 25px;
		text-align: center;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.plate-item .field-preview {
		display: block;
		margin-top: 40px;
		height: 125px;
		line-height: 125px;
		text-align: center;
		vertical-align: middle;
	}
	.plate-item .field-preview img {
		max-height: 100%;
		max-width: 100%;
		background-image: linear-gradient(45deg, #cccccc 25%, transparent 25%), linear-gradient(-45deg, #cccccc 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #cccccc 75%), linear-gradient(-45deg, transparent 75%, #cccccc 75%);
		background-size: 20px 20px;
		background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
	}
	.plate-item .actions {
		color: #999999;
		display: inline-block;
		position: absolute;
		top: 6px;
		right: 0;
	}
	.plate-item .record-checkbox-container {
		display: inline-block;
		position: absolute;
		left: 10px;
		top: 0;
	}
	.plate-item .record-checkbox-container input {
		cursor: pointer;
	}
</style>
