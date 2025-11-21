# Contributing to Upcoming Meetings

## How to Contribute

To contribute to the Upcoming Meetings:

1. Fork the repository
2. Make your changes
3. Send a pull request to the main branch

Take a look at the [issues](https://github.com/bmlt-enabled/upcoming-meetings-bmlt/issues) for bugs that you might be able to help fix.

Once your pull request is merged, it will be released in the next version.

## Local Development Setup

To get things going in your local environment:

1. Run the following command to start the development environment:
   ```bash
   docker compose up
   ```

2. Set up your WordPress installation and remember your admin password.

3. Once it's running, log in to the admin panel and activate the "Upcoming Meetings" plugin.

4. You can now make edits to the `upcoming-meetings.php` file and changes will take effect instantly.

## Code Standards

Please make note of the `.editorconfig` file and adhere to it, as this will minimize formatting errors. If you are using PHPStorm, you will need to install the EditorConfig plugin.

### PHP Code Style

This project uses PHP CodeSniffer (phpcs) for code style enforcement. The coding standards are configured in `.phpcs.xml`.

To check your code for style violations:
```bash
make lint
```

To automatically fix code style issues:
```bash
make fmt
```

Make sure to run these commands before submitting your pull request to ensure your code adheres to the project's coding standards.

## Release Tagging

- If a release is tagged with `beta`, it will be pushed as a zip file in the GitHub release
- If it's not tagged as beta, it will be published to the WordPress directory as a release in addition to GitHub
