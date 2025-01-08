<script>
    import {onMount} from 'svelte';
    import Base from './Base.svelte';

    export let value = '';
    export let mode = 'detail';
    export let name = '';
    export let model = null;
    export let params = {};

    export let rowsMin = 2;
    export let rowsMax = 10;
    export let detailMaxLength = 400;
    export let detailMaxNewLineCount = 10;
    export let maxLength = null;
    export let countBytesInsteadOfCharacters = false;
    export let autoHeightDisabled = false;
    export let useDisabledTextareaInViewMode = false;
    export let seeMoreDisabled = params.seeMoreDisabled || false;

    let seeMoreText = false;
    let textElement;
    let currentLength = 0;
    let hasError = false;

    $: rows = autoHeightDisabled ? rowsMax : rowsMin;
    $: showFullText = seeMoreText || seeMoreDisabled || mode !== 'detail';

    onMount(() => {
        if (!autoHeightDisabled && textElement) {
            controlTextareaHeight();
        }
    });

    function getValueForDisplay(text) {
        if (!text) return '';

        if ((mode === 'detail' && !useDisabledTextareaInViewMode) || mode === 'list') {
            if (!showFullText) {
                text = text.toString();
                let isCut = false;

                if (text.length > detailMaxLength) {
                    text = text.substr(0, detailMaxLength);
                    isCut = true;
                }

                const nlCount = (text.match(/\n/g) || []).length;
                if (nlCount > detailMaxNewLineCount) {
                    const lines = text.split('\n').slice(0, detailMaxNewLineCount);
                    text = lines.join('\n');
                    isCut = true;
                }

                if (isCut) {
                    text += ' ...\n[see more]';
                }
            }
        }

        return text;
    }

    function controlTextareaHeight(lastHeight) {
        if (!textElement) return;

        const scrollHeight = textElement.scrollHeight;
        const clientHeight = textElement.clientHeight;

        if (typeof lastHeight === 'undefined' && clientHeight === 0) {
            setTimeout(() => controlTextareaHeight(), 10);
            return;
        }

        if (clientHeight === lastHeight) return;

        if (scrollHeight > clientHeight + 1) {
            let rows = textElement.rows;

            if (rowsMax && rows >= rowsMax) return;

            textElement.rows = rows + 1;
            controlTextareaHeight(clientHeight);
        }

        if (textElement.value.length === 0) {
            textElement.rows = rowsMin;
        }
    }

    function updateTextCounter() {
        if (!maxLength) return;

        const textLength = getRealLength(value);
        currentLength = textLength;

        hasError = maxLength < textLength;
    }

    function getRealLength(text) {
        if (!text) return 0;

        if (countBytesInsteadOfCharacters) {
            return encodeURI(text).split(/%..|./).length - 1;
        }
        return text.toString().length;
    }

    function handleInput(event) {
        value = event.target.value;

        if (mode === 'edit') {
            updateTextCounter();
            if (!autoHeightDisabled) {
                controlTextareaHeight();
            }
        }
    }

    function handleSeeMore() {
        seeMoreText = true;
    }
</script>

<Base {name} {value} {mode} {model} {params}>
    <slot>
        {#if mode === 'detail' || (mode === 'detail' && useDisabledTextareaInViewMode)}
            {#if useDisabledTextareaInViewMode}
            <textarea
                    bind:this={textElement}
                    {name}
                    value={getValueForDisplay(value)}
                    rows={rows}
                    disabled={true}
                    class="form-control"
            ></textarea>
            {:else}
                {#if getValueForDisplay(value)}
                    <pre>{getValueForDisplay(value)}</pre>
                    {#if !showFullText}
                        <a href="javascript:" on:click={handleSeeMore}>See more</a>
                    {/if}
                {/if}
            {/if}
        {:else if mode === 'edit'}
        <textarea
                bind:this={textElement}
                {name}
                value={value || ''}
                rows={rows}
                on:input={handleInput}
                class="form-control"
                class:with-text-length={maxLength}
                class:error={hasError}
        ></textarea>
            {#if maxLength}
                <div class="text-counter">
                    <span class="current" class:error={hasError}>{currentLength}</span>
                    <span class="maximum">/{maxLength}</span>
                </div>
            {/if}
        {:else if mode === 'list'}
            <span>{getValueForDisplay(value)}</span>
        {/if}
    </slot>
</Base>

<style>
    textarea {
        width: 100%;
        resize: vertical;
    }

    textarea.error {
        border-color: red;
    }

    pre {
        white-space: pre-wrap;
        word-wrap: break-word;
        margin: 0;
        font-family: inherit;
    }

    .text-counter {
        text-align: right;
        font-size: 0.85em;
        color: #666;
        margin-top: 2px;
    }

    .text-counter .error {
        color: red;
    }
</style>