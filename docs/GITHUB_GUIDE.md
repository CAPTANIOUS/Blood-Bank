# How our team uses Git and GitHub

## Rules
1. **One file, one owner.** The owner of every file is listed in `docs/TEAM_CONTRIBUTION.md`. Nobody edits another member's file; ask the owner. This is why we never get merge conflicts.
2. **Never work on `main`.** Every piece of work has its own branch.
3. **Every branch becomes a Pull Request (PR).** A teammate reads it (Files changed), approves it, and the author merges it.
4. **Merge with "Create a merge commit"**, never "Squash", so every small commit stays visible with its author.
5. **Commit from your own laptop with your own GitHub account.** Never change commit dates.
6. **Small commits.** One commit = one idea, with a message that says what it adds.

## Branch names
| Prefix | Use | Example |
|---|---|---|
| `feature/` | new part of the project or an upgrade | `feature/find-donors` |
| `docs/` | documentation only | `docs/er-diagram-and-schema` |

## Commit messages
Start with a verb, say what it adds, no full stop: `Add donor dashboard`, `Show last donation and days since in donation history`.

## The routine
```
git checkout main
git pull
git checkout -b feature/your-branch
(work and save)
git status
git add <files>
git commit -m "Add something clear"
git push -u origin feature/your-branch
```
Then open the Pull Request on GitHub, ask a teammate to review it, merge it, and run `git checkout main` and `git pull`.

## Upgrades with Issues
For an upgrade, first open an **Issue** ("Add CSV export of donors"). In the Pull Request description write `Closes #<issue number>`; the Issue closes by itself when the PR is merged.

## Versions
When the work is finished, the team leader creates the tag `v1.1` and a GitHub Release.
