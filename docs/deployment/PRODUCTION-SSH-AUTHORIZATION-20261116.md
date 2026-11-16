# RYCOOP Production SSH — one-time connection authorization

## Status
A GitHub Actions **read-only SSH connectivity** workflow has been added as source code, but it **cannot connect** until an operator provisions the key and GitHub Environment secrets. A PR alone does not install the workflow on the default branch. No deploy, migration, reboot, permission escalation, Nginx edits or production file writes are performed by the workflow.

Observed from operator's VPS screenshot on 2026-11-16:
- User/host: coopadmin@metrabyte
- sshd: publickeyauthentication yes, passwordauthentication yes, permitrootlogin yes
- git 2.43.0, nginx -t succeeded
- /var/www/rycoopweb/production-releases exists

**Security caveat:** `permitrootlogin yes` and password authentication being enabled merit a separate access review. Do not turn these off until existing public-key emergency access is verified in a second independent session.

## 1. Generate a dedicated private key on the operator's trusted workstation
Use the workstation with existing authorized SSH access, **not a shared server and not ChatGPT**:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/rycoop_gha_production -C rycoop-github-actions -N ''
```

On a trusted Windows PowerShell terminal, the equivalent is `ssh-keygen -t ed25519 -f "$env:USERPROFILE\.ssh\rycoop_gha_production" -C rycoop-github-actions`.

Keep `rycoop_gha_production` PRIVATE. Put its whole contents into the GitHub Environment secret `RYCOOP_SSH_PRIVATE_KEY`, **never commit or paste it into a chat**. Its companion `.pub` is safe to copy.

## 2. Provision minimum SSH login rights
On VPS as coopadmin, create a dedicated account *only if approved by the VPS administrator*. This initial read-only preflight may also use the current authorized coopadmin account; use a separate constrained deploy account before granting any write permission.

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
touch ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
# Append the PUBLIC .pub key content as a single line using your trusted SSH client.
# Do not modify SSH daemon settings and do not replace existing authorized_keys lines.
```

For the read-only verification, the authorized key must authenticate and run `bash -se` with no prompt. Do not give the GitHub key root or passwordless sudo rights.

## 3. Verify host key using an independent trusted channel
On the existing authenticated VPS shell, inspect the machine's host public key fingerprint:

```bash
sudo ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
sudo cat /etc/ssh/ssh_host_ed25519_key.pub
```

From the operator workstation, run `ssh-keyscan -t ed25519 YOUR_PUBLIC_HOSTNAME` and independently compare its fingerprint to the one shown **on the authenticated VPS**. `ssh-keyscan` alone is not a secure host identity proof. Save the validated full known_hosts line in GitHub Environment secret `RYCOOP_SSH_KNOWN_HOSTS` (hostname and key must match the actual connection target).

## 4. GitHub Environment
In `pharmacisttom/rycoopweb` > Settings > Environments > `production`, enable required reviewers and restrict allowed deployment branches. Configure Environment Secrets:

- `RYCOOP_SSH_HOST`: verified hostname or IP (no scheme)
- `RYCOOP_SSH_PORT`: e.g. 22
- `RYCOOP_SSH_USER`: dedicated authorized login
- `RYCOOP_SSH_PRIVATE_KEY`: private ed25519 key (secret)
- `RYCOOP_SSH_KNOWN_HOSTS`: verified single known_hosts record (secret)

Restrict who can manage repository Actions secrets and Environments. Verify review protections with your repository plan/policy. Never put the secret value in the workflow log.

## 5. Run only the read-only probe
After PR review/merge to the repository default branch, launch Actions > `Production SSH connection preflight (READ ONLY)` > Run workflow. The workflow requires the `production` environment, verifies a pinned host key, runs a read-only inspection of /var/www/rycoopweb/production-releases, and stops. It **does not deploy**.

If permissions, SSH authentication or host identity fail, do not weaken security settings to get a green check. Fix the corresponding account, key or pinned host fingerprint through the trusted administrator channel.

## Separate deployment gate
Before any actual switch to Production, verify the exact reviewed release SHA, compare routes and assets, perform a restore-tested backup, verify TEST HTTP/MFA/RBAC, ensure private activity galleries are not publicly accessible, make a release transaction plan and atomic rollback. Do **not** run MySQL migrations or auto-approve/publish board or activity content through this SSH probe. The existing app's member/dividend/financial records are out of scope.

## About page recovery
Earlier React About content restoration is in draft PR #31. The old detailed PHP template is not automatically served by the new SPA. Restore and test the React About page in the selected release; do not revert the entire application to the old PHP version.
