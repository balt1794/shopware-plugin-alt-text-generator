import './page/alt-text-generator-index';

Shopware.Module.register('alt-text-generator', {
    type: 'plugin',
    name: 'alt-text-generator',
    title: 'alt-text-generator.general.title',
    description: 'alt-text-generator.general.description',
    color: '#795BEF',
    icon: 'regular-image',

    routes: {
        index: {
            component: 'alt-text-generator-index',
            path: 'index',
            meta: {
                parentPath: 'sw.content.index',
                privilege: 'media.viewer',
            },
        },
    },

    navigation: [{
        id: 'alt-text-generator',
        label: 'alt-text-generator.general.title',
        color: '#795BEF',
        path: 'alt.text.generator.index',
        parent: 'sw-content',
        position: 100,
        privilege: 'media.viewer',
    }],
});
