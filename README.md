# WP Term Timestamps

This is a simple plugin that records timestamps when terms are created or modified, and the ID of the user who made the
modification. 

### Term Creation
Whenever a term is created the following data will be stored in the term's term_meta: 

Terms that were created before this plugin was activated will, of course, not have this meta stored.

* **meta_key:** `created_by`
* **meta_value:** User ID for who created the term

* **meta_key:** `created_timestamp`
* **meta_value:** Timestamp for when the term was created

### Term Updates
Whenever a term is updated, the following data will be stored in the term's term_meta:

* **meta_key:** `last_modified_timestamp`
* **meta_value:** Timestamp for when the term was most recently modified


* **meta_key:** `last_modified_by`
* **meta_value:** User ID for who most recently modified the term.


* **meta_key:** `modifications`
* **meta_value:** Array which includes timestamp and the user ID for the user who modified the term

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
        modifications {
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

You may first need to install the unit tests like so: 

`bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]`

