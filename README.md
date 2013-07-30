wp-deploy-flow
==============

A wp-cli command to deploy your wordpress instance.

Dependencies
============

* Wordpress
* wp-cli : http://wp-cli.org
* rsync

Install - Usage
===============

Check here : http://demental.info/blog/2013/04/09/a-decent-wordpress-deploy-workflow/

1. Setting up environments

You can create as many environments as you want, all the environments must be setup in your wp-config.php file, with prefixed constants.

For example if you want to create a staging environment, create all the necessary constants to configure it such as : STAGING_DB_HOST, STAGING_DB_USER, STAGING_URL and so on .... And copy all those configuration constats to all the other environments you want to interact with.

2. Available constants:

| ._Name | ._description | mandatory | default |
| [ENV]_DB_HOST | Database host for the environment | Yes | |
| [ENV]_DB_USER | Database user for the environment | Yes | |
| [ENV]_DB_NAME | Database name for the environment | Yes | |
| [ENV]_DB_PORT | Database port for the environment | No | 3306|
| [ENV]_DB_PASSWORD | Database password for the environment | No |
| [ENV]_SSH_DB_HOST |  If you need to connect to the destination database through SSH | No | |
| [ENV]_SSH_DB_USER |  If you need to connect to the destination database through SSH | No | |
| [ENV]_SSH_DB_PATH |  If you need to connect to the destination database through SSH | No | |
| [ENV]_SSH_DB_PORT |  If you need to connect to the destination database through SSH | No | 22 |
| [ENV]_SSH_HOST | SSH host to sync with Rsync | No | |
| [ENV]_SSH_USER | SSH user to sync with Rsync | No | |
| [ENV]_SSH_PORT | SSH port to sync with Rsync | No | 22 |
| [ENV]_PATH | Server path for the environment (used to reconfigure the Wordpress database) | Yes | |
| [ENV]_URL| Url of the Wordpress install for this environment (used to reconfigure the Wordpress database) | Yes | |
| [ENV]_EXCLUDES| Add files to exclude from rsync (a good idea is - temporarily I hope - to remove .htaccess to avoid manual flush rewrite)| No | |

3. Local deployment

wp-deploy-flow command is a nice tool to have a draft copy of your website, play with your draft, do whatever mistake and roll back from production to staging, or for preparing a big update and deploy in a snap.
Although it's best to have separate servers for each environments, you still can have your draft environment on the same HTTP server, in a subfolder or a subdomain.
For same-server environments, the configuration is much simpler : you just need to fill the PATH, URL, DB_HOST / USER / NAME / PASSWORD for each environment, SSH will not be used in this case.
If one environemnt is in a subfolder of the other, it will be automatically excluded from rsync copy to avoid infinite recursion.

4. Usage

wp-deploy-flow comes includes:
* four subcommands : pull,  pull_files, push and push_files
* one flag : --dry-run as you can guess this flag allows you to see what SSH commands will be executed before actually launching them.

All subcommands have the same signature :

```
wp deploy <subcommand> <environment> [--dry-run]
```


Testing
=======

Shame on me... No automated tests, this is manually tested, but I recently redesigned the code so it should be easier now to cover the project (at least the core classes : puller and pusher).

If you want to contribute, be kind, send a PR I will be happy to review and merge !