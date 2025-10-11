import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ArticlePublicShow from '@/pages/Articles/ArticlePublicShow.vue';
import { createI18n } from 'vue-i18n';

// Mock Inertia
vi.mock('@inertiajs/vue3', () => ({
    router: {
        visit: vi.fn(),
    },
    usePage: () => ({
        props: {
            tenant: { alias: 'test-tenant' },
        },
    }),
    Link: {
        name: 'Link',
        template: '<a><slot /></a>',
    },
}));

// Mock tenantAwareRoute
vi.mock('@/utils/tenantAwareRoute', () => ({
    default: (name: string, params?: any) => `/${name}`,
}));

// Mock i18n
const i18n = createI18n({
    legacy: false,
    locale: 'en',
    messages: {
        en: {
            articles: {
                toggle_index: 'Toggle Index',
                index: 'Index',
                edit_article: 'Edit Article',
                back_to_world: 'Back to World',
            },
            common: {
                close: 'Close',
            },
        },
    },
});

describe('ArticlePublicShow - Responsive Behavior', () => {
    const mockArticle = {
        id: 1,
        alias: 'test-article',
        campaign_id: 1,
        name: 'Test Article',
        status: 'published',
        is_draft: false,
        has_index: true,
        has_sidebar: true,
    };

    const mockArticleIndex = [
        {
            id: 1,
            name: 'Chapter 1',
            is_directory: true,
            children: [
                {
                    id: 2,
                    name: 'Section 1.1',
                    is_directory: false,
                },
            ],
        },
    ];

    describe('Index Toggle Button in Topbar', () => {
        it('should render index toggle button when hasIndex is true', () => {
            const wrapper = mount(ArticlePublicShow, {
                props: {
                    article: mockArticle,
                    articleIndex: mockArticleIndex,
                    isOwner: false,
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        GuestLayout: {
                            template: '<div><slot name="topbar-left" /><slot /></div>',
                        },
                        ArticlesIndex: true,
                        ArticlePublicHeader: true,
                        ArticlePublicContent: true,
                        ArticlePublicSidebar: true,
                        ArticlePublicFooter: true,
                        ArticlePublicComments: true,
                    },
                },
            });

            // Find the button in topbar-left slot
            const toggleButton = wrapper.find('button[title="Toggle Index"]');
            expect(toggleButton.exists()).toBe(true);
            expect(toggleButton.classes()).toContain('xl:hidden');
        });

        it('should not render index toggle button when hasIndex is false', () => {
            const wrapper = mount(ArticlePublicShow, {
                props: {
                    article: { ...mockArticle, has_index: false },
                    articleIndex: [],
                    isOwner: false,
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        GuestLayout: {
                            template: '<div><slot name="topbar-left" /><slot /></div>',
                        },
                        ArticlesIndex: true,
                        ArticlePublicHeader: true,
                        ArticlePublicContent: true,
                        ArticlePublicSidebar: true,
                        ArticlePublicFooter: true,
                        ArticlePublicComments: true,
                    },
                },
            });

            const toggleButton = wrapper.find('button[title="Toggle Index"]');
            expect(toggleButton.exists()).toBe(false);
        });

        it('should toggle index overlay when button is clicked', async () => {
            const wrapper = mount(ArticlePublicShow, {
                props: {
                    article: mockArticle,
                    articleIndex: mockArticleIndex,
                    isOwner: false,
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        GuestLayout: {
                            template: '<div><slot name="topbar-left" /><slot /></div>',
                        },
                        ArticlesIndex: true,
                        ArticlePublicHeader: true,
                        ArticlePublicContent: true,
                        ArticlePublicSidebar: true,
                        ArticlePublicFooter: true,
                        ArticlePublicComments: true,
                    },
                },
            });

            const toggleButton = wrapper.find('button[title="Toggle Index"]');
            expect(wrapper.vm.isIndexOverlayOpen).toBe(false);

            await toggleButton.trigger('click');
            expect(wrapper.vm.isIndexOverlayOpen).toBe(true);

            await toggleButton.trigger('click');
            expect(wrapper.vm.isIndexOverlayOpen).toBe(false);
        });
    });

    describe('Desktop Index Sidebar', () => {
        it('should have xl:block class for desktop visibility', () => {
            const wrapper = mount(ArticlePublicShow, {
                props: {
                    article: mockArticle,
                    articleIndex: mockArticleIndex,
                    isOwner: false,
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        GuestLayout: {
                            template: '<div><slot name="topbar-left" /><slot /></div>',
                        },
                        ArticlesIndex: true,
                        ArticlePublicHeader: true,
                        ArticlePublicContent: true,
                        ArticlePublicSidebar: true,
                        ArticlePublicFooter: true,
                        ArticlePublicComments: true,
                    },
                },
            });

            // Find the desktop index sidebar
            const desktopIndex = wrapper.find('aside.hidden.xl\\:block');
            expect(desktopIndex.exists()).toBe(true);
        });

        it('should not render desktop index when hasIndex is false', () => {
            const wrapper = mount(ArticlePublicShow, {
                props: {
                    article: { ...mockArticle, has_index: false },
                    articleIndex: [],
                    isOwner: false,
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        GuestLayout: {
                            template: '<div><slot name="topbar-left" /><slot /></div>',
                        },
                        ArticlesIndex: true,
                        ArticlePublicHeader: true,
                        ArticlePublicContent: true,
                        ArticlePublicSidebar: true,
                        ArticlePublicFooter: true,
                        ArticlePublicComments: true,
                    },
                },
            });

            const desktopIndex = wrapper.find('aside.hidden.xl\\:block');
            expect(desktopIndex.exists()).toBe(false);
        });
    });

    describe('Mobile Index Overlay', () => {
        it('should have xl:hidden class for mobile overlay', () => {
            const wrapper = mount(ArticlePublicShow, {
                props: {
                    article: mockArticle,
                    articleIndex: mockArticleIndex,
                    isOwner: false,
                },
                global: {
                    plugins: [i18n],
                    stubs: {
                        GuestLayout: {
                            template: '<div><slot name="topbar-left" /><slot /></div>',
                        },
                        ArticlesIndex: true,
                        ArticlePublicHeader: true,
                        ArticlePublicContent: true,
                        ArticlePublicSidebar: true,
                        ArticlePublicFooter: true,
                        ArticlePublicComments: true,
                    },
                },
            });

            // Trigger overlay open
            wrapper.vm.isIndexOverlayOpen = true;
            wrapper.vm.$nextTick();

            // Check that overlay elements have xl:hidden class
            const overlayBackground = wrapper.find('.fixed.inset-0.xl\\:hidden');
            expect(overlayBackground.exists()).toBe(true);
        });
    });
});

