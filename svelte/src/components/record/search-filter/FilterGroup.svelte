<script lang="ts">
    export let className: string | null = null;
    export let opened: boolean = false;
    export let title: string;

    function toggleOpened(): void {
        opened = !opened;
    }
</script>

<div class={className}>
    <details open={opened}>
        <summary on:click|preventDefault={toggleOpened}>
            <i class="ph ph-caret-right"></i>
            <h5>{title}</h5>
        </summary>

        <div class="details-inner">
            <slot></slot>
        </div>
    </details>
</div>

<style>
    summary {
        list-style: none;
        cursor: pointer;
        user-select: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    summary i {
        transition: transform 0.2s ease;
    }

    details[open] summary i {
        transform: rotate(90deg);
    }

    .details-inner {
        padding-left: 3px;
    }

    .details-inner :global(.checkbox) {
        margin-top: 0;
        margin-bottom: 5px;
    }

    .details-inner :global(.checkbox label input + span) {
        vertical-align: middle;
    }
</style>