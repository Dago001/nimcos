# Administrator Guide

For NIMCOS election officials: Super Admin, Election Administrator, Returning Officer and Auditor.

Sign in at `https://<your-site>/admin` with your email, password and (when enabled) your authenticator app code. Actions that affect the election outcome ask for your password again (and your code, if you use MFA). This is intentional.

## Who does what

| Task | Role |
|---|---|
| Manage administrators, roles, settings | Super Admin |
| Voter register, imports, verification | Election Administrator (and Super Admin) |
| Create elections, positions, candidates, voter roll | Election Administrator |
| Open / close voting | Election Administrator or Returning Officer |
| Calculate, verify, resolve ties, **publish results** | Returning Officer only |
| Read-only monitoring, reports, audit | Auditor |

## 1. Prepare the voter register

1. **Import voters → Download template.** Fill in one row per member. Required columns: SERVICE NUMBER, SURNAME, FIRST NAME, EMAIL. Each email address must belong to one member only (it receives their code). PHONE NUMBER is optional. In Excel, format the Service Number column as Text so leading zeros are kept. RANK must be a real NIS rank (the full title, e.g. "Deputy Comptroller of Immigration", or the abbreviation, e.g. "DCI") — rows with an unrecognised rank are rejected.
2. **Upload and validate.** The preview shows total, new, updated, duplicate, invalid and rejected rows. Nothing changes yet.
3. Download the **error report**, correct the rows at source, and re-upload them if needed.
4. Tick the confirmation and **Confirm and import**. The whole file is applied in one transaction: if anything fails, nothing changes. Every new member from the import is marked **Verified** automatically (the register file is treated as the authorised source), so they can be authorised to vote as soon as they're on the register.

Individual members can be added, edited, suspended or reinstated from the voter list. A member added or edited by hand, one row at a time, still starts **Unverified** and needs to be verified from the voter list before they can be authorised — only members that arrive through an approved import bulk are verified automatically. Changing a member's name, Service Number or contact details resets verification.

## 2. Create the election

1. **Create election:** name (e.g. NIMCOS 2026 ELECTIVE CONGRESS), code (e.g. NIMCOS-2026-EC), voting opens / closes (West Africa Time), automatic opening/closing, and **Show live vote counts** (on by default: the dashboard shows every contestant with a running count while voting is open, to officials with the "View results" permission; untick to keep results sealed until close).
2. **Positions tab → Add all active positions.** This adds the 14 offices. Adjust seats, order or "required" if needed. (The catalogue itself is under *Positions*.)
3. **Candidates:** add each candidate under the correct position, with rank (chosen from the fixed NIS rank list, Comptroller of Immigration Service down to Immigration Assistant 3), command, a short profile and a photograph (JPEG/PNG/WebP, at least 200×200 px). Numbers are assigned automatically. Withdrawn or disqualified candidates never appear on the ballot.
4. **Eligibility tab → Authorise all verified members**, or authorise individuals by Service Number. Eligibility is per election: voting in one election does not affect the next.
5. On the **Overview** tab, fix anything listed under *Not ready*, then **Schedule election** (password required). The ballot is locked from this point. Use *Return to draft* to correct it before voting opens.

## 3. Election day

- **Opening.** If automatic opening is on, voting starts at the scheduled time. Otherwise an official uses **Open election**. The confirmation shows the election, start, end, eligible voters and number of candidates.
- **Live monitor:** eligible voters, votes cast, not yet voted, turnout, last vote, active/completed/expired sessions, voting by hour and by command. It refreshes every 15 seconds and **never** shows candidate figures.
- **Security alerts:** review open alerts regularly. An alert means "look at this", not "fraud". Mark each one *Reviewed* or *Dismissed* with notes.
- **Voter problems:**
  - *Did not receive a code:* confirm the email address on the register (Voter → edit) and ask the voter to check their spam folder. The voter can request a new code after 60 seconds, up to 3 codes in 15 minutes.
  - *"We could not give you access":* check that the member is on the voter list, verified and active, and on this election's roll (Eligibility tab).
  - *Suspending during voting:* allowed (Eligibility → Suspended, with a reason). Adding new voters after opening is not allowed.
- **Closing.** Voting stops automatically at the end time. Ballots are refused after the end time even before the status changes. **Close election** ends voting early and cannot be undone.

## 4. Results (Returning Officer)

On the election's **Results** tab:

1. **Calculate results.** This counts every recorded ballot. No figures can be typed or edited anywhere in the system.
2. **Run verification.** This performs an independent recount and checks that ballots = voters who voted = used ballot tokens, and that no ballot exceeds a position's seats. Publication is impossible until verification passes.
3. **Ties.** A position marked **TIE DETECTED** needs a recorded resolution under the NIMCOS election rules: *Runoff pending*, *Runoff held*, *Draw of lots* or *Committee decision*, with the declared winner (if any) and a notes/minutes reference. Vote counts are not changed.
4. **Publish official results** (password required). The results appear on the public `/results` page and are frozen permanently.

Recalculating before publication discards the tallies and tie resolutions and requires verification again.

## 5. Reports

**Reports** provides the voter register (with voted / not voted), election turnout (overall and by command), results (marked *provisional* until published) and the audit report, as PDF, Excel or CSV. Every download is logged. No report shows how any individual voted.

## 6. Audit and oversight

- **Audit logs:** every sign-in, OTP event, register change, election action, ballot cast (voter + election only), result action and permission change. Filter by date, user, action, entity, result or IP.
- **Verify integrity of the log** recomputes the hash chain. A broken chain means the database was altered outside the application: escalate immediately.
- After publication, **archive** the election to mark it historical. All records are retained.

## 7. Administrators and settings (Super Admin)

- **Users → Add administrator:** choose roles. A temporary password is emailed to the new administrator; they must change it and (in production) enrol MFA at first sign-in.
- **Roles & permissions:** adjust the matrix if the organisation's rules require it. At least one active administrator must keep *Manage administrators*.
- **System settings:** OTP validity and attempts, ballot session timeout, MFA requirement, the support contact shown to voters, and the office address, phone, email and support hours shown on the home page.

## 8. Announcements (Super Admin)

**Administration → Announcements → Post announcement** publishes a notice to voters on the home page, the sign-in page and the results pages.

- **Show as:** *Pop-up* (opens once for each visitor, and again if you edit it), *Scrolling ticker* (a moving line across the top of the page, which visitors can pause), or both.
- **Level:** Information (green), Important (amber) or Urgent (red, listed first).
- **Show from / Show until (WAT):** optional. Leave both empty to show it immediately until you switch it off.
- **Link:** optional; it must start with `https://` or be a page on this site (for example `/results`).
- Announcements are plain text. Nothing is shown to a voter while they are filling in or reviewing their ballot.
- Never ask voters for their verification code, password or payment in an announcement. Every post, change and deletion is recorded in the audit log.
- The permission is *Post announcements to voters*; by default only the Super Admin has it.
