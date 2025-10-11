import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ArticlePublicSidebar from '@/components/custom/models/article/ArticlePublicSidebar.vue';
import { createI18n } from 'vue-i18n';

// Mock i18n
const i18n = createI18n({
    legacy: false,
    locale: 'en',
    messages: {
        en: {},
    },
});

describe('ArticlePublicSidebar - Responsive Width', () => {
    const mockArticle = {
        id: 1,
        alias: 'test-article',
        campaign_id: 1,
        name: 'Test Article',
        status: 'published',
        is_draft: false,
        has_index: false,
        has_sidebar: true,
        sidebar_top: '<p>Top content</p>',
        sidebar_bottom: '<p>Bottom content</p>',
    };

    describe('Responsive Width Classes', () => {
        it('should have w-full on mobile and lg:w-[280px] on desktop', () => {
            const wrapper = mount(ArticlePublicSidebar, {
                props: {
                    article: mockArticle,
                    sidebarTopBlocks: [],
                    sidebarBottomBlocks: [],
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        Card: {
                            template: '<div><slot name="content" /></div>',
                        },
                        BlockWrapper: true,
                    },
                },
            });

            const sidebar = wrapper.find('aside');
            expect(sidebar.exists()).toBe(true);
            expect(sidebar.classes()).toContain('w-full');
            expect(sidebar.classes()).toContain('lg:w-[280px]');
        });

        it('should not render when has_sidebar is false', () => {
            const wrapper = mount(ArticlePublicSidebar, {
                props: {
                    article: { ...mockArticle, has_sidebar: false },
                    sidebarTopBlocks: [],
                    sidebarBottomBlocks: [],
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        Card: {
                            template: '<div><slot name="content" /></div>',
                        },
                        BlockWrapper: true,
                    },
                },
            });

            const sidebar = wrapper.find('aside');
            expect(sidebar.exists()).toBe(false);
        });

        it('should not render when no content is present', () => {
            const wrapper = mount(ArticlePublicSidebar, {
                props: {
                    article: {
                        ...mockArticle,
                        sidebar_top: null,
                        sidebar_bottom: null,
                    },
                    sidebarTopBlocks: [],
                    sidebarBottomBlocks: [],
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        Card: {
                            template: '<div><slot name="content" /></div>',
                        },
                        BlockWrapper: true,
                    },
                },
            });

            const sidebar = wrapper.find('aside');
            expect(sidebar.exists()).toBe(false);
        });

        it('should render when sidebar blocks are present', () => {
            const mockBlocks = [
                {
                    id: 1,
                    type: 'text',
                    content: 'Sidebar block',
                    disposition: 'sidebar_top',
                    order: 1,
                },
            ];

            const wrapper = mount(ArticlePublicSidebar, {
                props: {
                    article: {
                        ...mockArticle,
                        sidebar_top: null,
                        sidebar_bottom: null,
                    },
                    sidebarTopBlocks: mockBlocks,
                    sidebarBottomBlocks: [],
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        Card: {
                            template: '<div><slot name="content" /></div>',
                        },
                        BlockWrapper: true,
                    },
                },
            });

            const sidebar = wrapper.find('aside');
            expect(sidebar.exists()).toBe(true);
        });
    });
});

