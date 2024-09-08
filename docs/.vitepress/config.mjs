import { defineConfig } from 'vitepress'

// https://vitepress.dev/reference/site-config
export default defineConfig({
  title: "Laravel-Elasticsearch",
  description: "An Elasticsearch implementation of Laravel's Eloquent ORM",
  themeConfig: {
    // https://vitepress.dev/reference/default-theme-config
      logo: '/logo.svg',
      nav: [
          { text: 'Home', link: '/' },
          { text: 'Features', link: '/features' }
      ],

      sidebar: [
          {
              text: 'Introduction',
            items: [
               { text: 'Getting Started', link: '/'  },
              ]
          },
          {
              text: 'Eloquent',
              items: [
                  { text: 'The Base model', link: '/eloquent/the-base-model'  },
                  { text: 'Querying Models', link: '/eloquent/querying-models'  },
                  { text: 'Saving Models', link: '/eloquent/saving-models'  },
                  { text: 'Deleting Models', link: '/eloquent/deleting-models'  },
                  { text: 'Ordering & Pagination', link: '/eloquent/ordering-and-pagination'  },
                  { text: 'Distinct & GroupBy', link: '/eloquent/distinct'  },
                  { text: 'Aggregation', link: '/eloquent/aggregation'},
                  { text: 'ES Specific Queries', link: '/eloquent/es-specific'},
                  { text: 'Nested Queries', link: '/eloquent/nested-queries'},
                  { text: 'Full-Text Search', link: '/eloquent/full-text-search'},
                  { text: 'Dynamic Indices', link: '/eloquent/dynamic-indices'},
              ]
          },
          {
              text: 'Relationships',
              items: [
                { text: 'Elasticsearch to Elasticsearch', link: '/relationships/es-es'},
                { text: 'Elasticsearch to MySQL', link: '/relationships/es-mysql'},
              ]
          },
          {
              text: 'Schema/Index',
              items: [
                { text: 'Migrations', link: '/schema/migrations'},
                { text: 'Re-indexing Process', link: '/schema/re-indexing'}
              ]
          },
          {
              text: 'Notes/Resources',
              items: [
                { text: 'Handling Errors', link: '/resources/handling-errors'},
                { text: 'Elasticsearch Quirks', link: '/resources/elasticsearch-quirks'}
              ]
          }
      ],

      socialLinks: [
          { icon: 'github', link: 'https://github.com/pdphilip/laravel-elasticsearch' }
      ]
  }
})
