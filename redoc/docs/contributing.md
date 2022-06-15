# CONTRIBUTION

How to contribute?
We're glad you're reading this because any good open source project can always use volunteers for the project. There are some rules to follow, but don't panic, it's easy!

If you haven't already, come and find us in our Slack(EXAMPLE). We want you to work on things you're passionate about.

Here are some essential resources:

[Technical documentation](https://docs.conductor-gateway.app/en/latest/)

## Reporting Bugs

___________________________________________________________________________________________________

Then, if it appears that it's a real bug, you may report it using Github by following these 3 points:

- Check if the bug is not already reported!
- A clear title to resume the issue
- A description of the workflow needed to reproduce the bug,

>__NOTE__: Don’t hesitate to give as much information as you can (OS, PHP version extensions...)

## Pull Requests

### Writing a Pull Request

Send a Pull Request with a clear list of what you've done. Please follow the Public Code coding conventions.(below) and make sure all your commits are atomic (one function per commit).

The Common Ground project follows [Symfony coding standards](https://symfony.com/doc/current/contributing/code/standards.html).
But don't worry, you can fix CS issues automatically using the [PHP CS Fixer](http://cs.sensiolabs.org/) tool:

```bash
php-cs-fixer.phar fix
```

And then, add fixed file to your commit before push.
Be sure to add only __your modified files__. If another files are fixed by cs tools, just revert it before commit.

### Sending a Pull Request

When you send a PR, just make sure that:

- You add valid test cases.
- Tests are green.
- You make a PR on the related documentation in the [Common Gateway](https://github.com/ConductionNL/commonground-gateway/tree/master/redoc/docs) repository.
- You make the PR on the same branch you based your changes on. If you see commits
  that you did not make in your PR, you're doing it wrong.
- Squash your commits into one commit. (see the next chapter)

Fill in the following header from the pull request template:

```markdown
| Q             | A
| ------------- | ---
| Bug fix?      | yes/no
| New feature?  | yes/no
| BC breaks?    | no
| Deprecations? | no
| Tests pass?   | yes
| Fixed tickets | #1234, #5678
| License       | MIT
| Doc PR        | api-platform/docs#1234

Always write a clear log message for your commits. One-line messages are fine for small changes, but more significant changes should be
should ideally look like:

`$ git commit -m "A short summary of the commit`

 A paragraph that describes what has changed and what the impact is."

## Squash your Commits

If you have 3 commits. So start with:

```bash
git rebase -i HEAD~3
```

An editor will be opened with your 3 commits, all prefixed by `pick`.

Replace all `pick` prefixes by `fixup` (or `f`) __except the first commit__ of the list.

Save and quit the editor.

After that, all your commits where squashed into the first one and the commit message of the first commit.

If you would like to rename your commit message type:

```bash
git commit --amend
```

Now force push to update your PR:

```bash
git push --force
```

## Coding conventions

This is open-source software. Think about the people reading the code and make sure it looks good to them.

We also use the [Public Code standards](https://publiccode.net/)

Thank you, the Common Gateway community!

# License and Copyright Attribution

When you open a Pull Request to the Common Gateway project, you agree to license your code under the [EUPL license](../LICENSE).

Be sure to you have the right to do that (if you are a professional, ask your company)!

If you include code from another project, please mention it in the Pull Request description and credit the original author.
