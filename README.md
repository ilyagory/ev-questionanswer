# ev-questionanswer

What: Qusetion-Answer site. 
How: Categories-Topics-Comments. Commentators could left comments anonymously.
Who: Administrator (manage categories, manage users), Manager (manage comments & topics in assigned categories), User, Anonymous.

# Install

1. Restore dump file `schema.sql`
1. `cp app/config{.example,}.ini`
1. Edit `app/config.ini` for your taste.
1. Create user via task: `php app/cli.php user create admin:1234 admin`. Where arguments are:
    * `admin:1234`
      username & password,

    * next `admin` means role (one of `admin`,`moder`,`user`)
