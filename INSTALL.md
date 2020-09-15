# Installation

1. Create a directory for W2 somewhere in your web server's document
   root path. It doesn't matter where. W2 requires PHP 5 or higher.
   
2. Upload the files from this repository to this directory.

3. Make sure that the "images" and "pages" directories are writable by your
   web server process.
   
4. You may or may not need to edit config.php. When you're ready, look in
   there for many additional configuration options
   (see below for more information).

You should now be ready to access your W2 installation.


# Configuration

The file config.php contains many options for you to customize your W2 setup.

A few examples:

The following line in config.php may be changed if you do not want the 
default page to be named 'Home':
```
define('DEFAULT_PAGE', 'Home');
```

The following line in config.php may be changed if you'd like to use a
different CSS stylesheet:
```
define('CSS_FILE', 'index.css');
```

The size of the edit textarea is controlled by:
```
define('EDIT_ROWS', 18);
```

## Security

W2 has the ability to prompt for a password before allowing access to the
site.  Two lines in config.php control this:
```
define('REQUIRE\_PASSWORD', false);
define('W2\_PASSWORD', 'secret');
```

Set `REQUIRE_PASSWORD` to true and set `W2_PASSWORD` to the password you'd like
to use.
**Note:** This is a very rudimentary way of authorizing access. A slightly more
secure variant is to leave `W2_PASSWORD` empty, and use the `W2_PASSWORD_HASH`
setting instead. Even better (and allowing for multiple different users) would
be the option to use Basic Authentication configured via your webserver
configuration.

## Git Integration

**Note:** The following assumes you are running W2 wiki on a Linux server, and
that you have shell access on your server. If you do not have such access,
this integration will not work.

**Prerequisite:** git needs to be installed on the server, and needs to be
callable via php's `exec` function.
To install git for example on an Ubuntu server, you would execute
`$ sudo apt install git` on a shell

The following assumes that the webserver is running under the `www-data`
user (Ubuntu). If you are running your server under a different distribution,
please consult its documentation or community regarding the proper username.

To enable changes in pages to be committed to a local git repository, you need to:

- Take note of the folder where the pages are stored (`PAGES_PATH` in config.php).
- Navigate to `PAGES_PATH` folder.
- Create a git repository: `$ sudo -u www-data git init`.
- Add all files there: `$ sudo -u www-data git add -A`.
- Do an initial commit: `$ sudo -u www-data git commit -m "Initial commit`.
- Set `GIT_COMMIT_ENABLED` to `true` in `config.php`.

To also enable pushing changes to a remote repository, you need to (in addition
to the steps above):

- Create a remote repository somewhere.
- Add this repository as remote in the repository in your `PAGES_PATH`:
  `$ sudo -u www-data git remote add origin [YOUR_REMOTE_REPO_URL]`
  (replace `[YOUR_REMOTE_REPO_URL]` with the publicly accessible URL of the remote).
- Set `GIT_PUSH_ENABLED` to `true` in `config.php`.

