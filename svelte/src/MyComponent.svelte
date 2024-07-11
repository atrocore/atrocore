<script lang="ts">
    export let name = 'world';

    function changeName() {
        console.log('13333')
        name = name + '_';
    }
</script>

<button class="ss" on:click={changeName}>Hello {name}!</button>

<style>
    .ss {
        color: purple;
    }
</style>