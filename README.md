# WP Term Timestamps

This is a simple plugin that records timestamps when terms are created or modified, and the ID of the user who made the
modification. 

### Term Creation
Whenever a term is created the following data will be stored in the term's term_meta: 

Terms that were created before this plugin was activated will, of course, not have this meta stored.

* **meta_key:** `created`
* **meta_value:** array which includes timestamp and the user ID for the user who created the term

### Term Updates
Whenever a term is updated, the following data will be stored in the term's term_meta:

* **meta_key:** `modified`
* **meta_value:** array which includes timestamp and the user ID for the user who modified the term

### WPGraphQL Support

This plugin provides support for WPGraphQL version 0.0.12 and newer (https://github.com/wp-graphql/wp-graphql).

When querying terms, the termObjects now have a `created` and `modified` field. 

Here is an example GraphQL query that would work with WPGraphQL and this plugin active. 

```
query { 
    categories {
        id
        link
        name
        created {
            time
            user {
                id
                username
            }
        }
        modified {
            time
            user {
                id
                username
            }
        }
    }
}
```

### Unit Tests
This plugin has Unit Tests. To run the tests, in the command line navigate to the plugin directory and run `phpunit`

