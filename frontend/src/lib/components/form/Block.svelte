<script>
    import { Card, CardContent, CardHeader, CardTitle } from '$lib/components/ui/card/index.ts';

    let { block, children } = $props();

    const { label, type, order, settings, fields } = block;
</script>


<Card>
    <CardHeader>
        <CardTitle>{label}</CardTitle>
    </CardHeader>
    <CardContent>
        <div class="grid gap-6 md:grid-cols-{settings.columns}">
            {@render children?.()}
        </div>
    </CardContent>
</Card>
