# Security Policy

| Version | 2026-09-23 |
|---|---|
| Relevant for | Users of AtroCore Platform, security researchers |

## 1. Purpose and Scope

1.1. This policy describes which versions of AtroCore receive bug fixes and security updates, how vulnerabilities are classified and remediated, how security updates are released, and how to report a vulnerability to us. It also sets out the rules for security research on AtroCore and our commitments to those who report vulnerabilities in good faith.

1.2. This policy applies to:

- the AtroCore platform and the modules provided by AtroCore GmbH, including the REST API and the Import/Export Feed interfaces
- the SaaS environments operated by AtroCore GmbH
- the public websites and web applications of AtroCore GmbH.

1.3. Vulnerabilities in third-party components contained in AtroCore, such as libraries, are within the scope of this policy, and we remediate them in AtroCore. Third-party software and services that are not part of AtroCore and are not operated by AtroCore GmbH are outside its scope; please report such issues directly to the vendor concerned.

## 2. Supported Versions

### 2.1. Security Updates

2.1.1. The AtroCore platform and each module have their own version numbers, each consisting of a major, a minor and a patch number. Security updates are provided for five years from the date on which the version concerned was first made available in the European Union:

| Product | Receives security updates for | Delivered as |
|---|---|---|
| AtroCore platform and free modules | Every major version | A patch release of the most recent minor version of that major version |
| Paid modules | Every minor version | A patch release of that minor version |

2.1.2. For the platform and free modules, run the most recent minor version of your major version to receive security updates. Within a major version, releases do not remove documented functionality, so upgrading to the most recent minor version of your major version does not require you to adapt your integrations.

2.1.3. Within a major version of the platform, new minor versions remain compatible with the module versions released for that major version. You can therefore always install the security updates of the platform, even if you do not update your modules.

2.1.4. For paid modules, you do not need a current update subscription to stay secure. You keep the minor version you hold and receive its security updates as patch releases of that minor version.

2.1.5. The end-of-support date of each version is listed in the AtroCore documentation at [help.atrocore.com](https://help.atrocore.com).

2.1.6. After the end of support of a version, it no longer receives security updates. To continue receiving them, upgrade to a version that is still supported.

### 2.2. Bug Fixes

2.2.1. Bug fixes are provided in the most recent release of the current major version. Users of an earlier major version receive bug fixes by upgrading to the current major version. Support agreements may provide for additional versions.

### 2.3. Bugs and Vulnerabilities

2.3.1. A defect that can be exploited to compromise the confidentiality, integrity or availability of AtroCore or of the data it processes is treated as a vulnerability, not as a bug. This includes defects that allow an attacker to crash the software or make it unavailable. Vulnerabilities are fixed in every supported version, as described in Section 2.1.

### 2.4. Customers with a Licence or Service Agreement

2.4.1. Customers who hold a licence for paid modules have additional rights under the AtroCore End-User License Agreement (EULA), Section 10.

2.4.2. Customers whose AtroCore environment is operated by AtroCore GmbH as a SaaS service do not need to take any action: we install security updates in these environments ourselves.

## 3. Severity Classification

3.1. We assess the severity of every confirmed vulnerability using the following classes:

| Severity | Description |
|---|---|
| Critical | Vulnerabilities that allow unauthorized access to sensitive personal data or confidential customer data, remote code execution or complete system compromise, or that are likely to result in a personal data breach |
| High | Vulnerabilities that allow significant unauthorized access to systems or data, privilege escalation, or bypass of core authentication or authorization controls |
| Medium | Vulnerabilities that present a meaningful security risk but require specific conditions or user interaction to exploit, or that have a limited impact on the confidentiality, integrity or availability of data |
| Low | Vulnerabilities with minimal exploitability or impact, including informational issues, minor misconfigurations, and findings that present a theoretical rather than a practical risk |

3.2. In addition, every confirmed vulnerability is scored using the Common Vulnerability Scoring System (CVSS) version 4.0. The classes correspond to the CVSS severity ratings as follows: Critical 9.0–10.0, High 7.0–8.9, Medium 4.0–6.9, Low 0.1–3.9. Where the CVSS score and the description in Section 3.1 point to different classes, we assign the higher class.

## 4. Release of Security Updates

4.1. Security updates are released as soon as they are ready, not on a fixed schedule. We aim to remediate confirmed vulnerabilities within the following periods, measured from the date on which the vulnerability is confirmed:

| Severity | Target remediation period |
|---|---|
| Critical | 72 hours |
| High | 7 calendar days |
| Medium | 30 calendar days |
| Low | 90 calendar days |

4.2. Security updates are free of charge. We always try to release security updates as separate patch releases that contain no new features. New features are included in a security update only where this is unavoidable, for example where the fix itself requires a change in functionality. A security update may also contain other bug fixes.

4.3. Security fixes are identified as such in the release notes. For every remediated vulnerability, whatever its severity, we publish a security advisory once a security update is available. The advisory describes the vulnerability and states the affected versions, the severity class and CVSS score, the version that fixes the vulnerability and any measures you should take in the meantime. We request a CVE identifier for every vulnerability published in an advisory.

4.4. Security advisories are published as GitHub Security Advisories in the repository concerned. You can subscribe to them by watching the repository for security alerts. Customers with a licence or service agreement are additionally informed of every security advisory by e-mail.

4.5. Details of a vulnerability are not published before a security update is available.

## 5. Reporting a Vulnerability

### 5.1. How to Report

5.1.1. **Please do not report security vulnerabilities through public GitHub issues, discussions or pull requests.**

5.1.2. Report vulnerabilities privately through GitHub, using **"Report a vulnerability"** in the **Security** tab of the repository concerned. This is our preferred channel: your report stays confidential and is linked directly to the advisory process.

5.1.3. Alternatively, report by e-mail to **security@atrocore.com** with the subject line **"Vulnerability Report – [brief description]"**.

5.1.4. Reports may be submitted in English or German.

5.1.5. Please include, where possible:

- a description of the vulnerability and its type (for example SQL injection, broken access control or information disclosure)
- the affected product, module, service or system, and its version
- step-by-step instructions to reproduce it
- the potential impact, including the data or functionality that could be affected
- supporting material such as screenshots, proof-of-concept code or HTTP request and response captures
- your contact details, so that we can follow up.

5.1.6. AtroCore does not currently operate a bug bounty programme. Reports are accepted on a voluntary basis and without financial compensation, unless agreed otherwise in writing.

### 5.2. What You Can Expect from Us

5.2.1. We acknowledge receipt of your report within **3 business days** and tell you our initial assessment, including the severity class, within **10 business days**.

5.2.2. Where a vulnerability cannot be remediated within the target period under Section 4.1, we tell you the reason and a revised target date.

5.2.3. We notify you once the vulnerability has been remediated and, with your consent, may credit you publicly.

5.2.4. We treat your report as confidential and do not share your personal information with third parties without your explicit consent, unless we are required to do so by law.

5.2.5. Security research carried out in good faith and in accordance with Section 5.3 is authorized by us. We will not take legal action or file a criminal complaint against you in connection with such research. If a third party takes legal action against you in connection with it, we will make it known that your research was authorized. This commitment cannot bind third parties or public authorities, and it does not cover activities outside Section 5.3.

### 5.3. Rules for Security Research

5.3.1. Act in good faith and conduct your research in a way that does not harm AtroCore GmbH, its customers or any individual whose data may be involved.

5.3.2. When investigating a potential vulnerability, do not:

- access, modify, delete or copy data beyond what is strictly necessary to demonstrate the vulnerability
- carry out denial-of-service attacks or any other action that degrades the availability or performance of our systems
- introduce malware, backdoors or any other malicious code
- carry out social engineering against our employees, contractors or customers
- use automated scanning tools against production environments without our prior written approval.

5.3.3. Use test or staging environments where available. If you are uncertain whether a particular testing activity is permitted, contact us before proceeding.

5.3.4. Give us a reasonable opportunity to remediate the vulnerability before disclosing it. We ask that you do not publish or share details for **90 calendar days** from your report. Where remediation takes longer, we will agree an extended disclosure timeline with you in good faith.

## 6. Out of Scope

6.1. The following are outside the scope of this policy and are generally not accepted as valid vulnerability reports:

- vulnerabilities in third-party software or services that are not part of AtroCore and are not operated by AtroCore GmbH (Section 1.3)
- issues that require physical access to a device or system
- social engineering or phishing attacks against our personnel
- findings obtained through volumetric denial-of-service attacks or load testing; defects that allow an attacker to crash the software or make it unavailable are vulnerabilities within the scope of this policy (Section 2.3)
- theoretical vulnerabilities without a demonstrated or plausible exploitation path
- missing security headers or TLS configuration issues on non-sensitive endpoints where the risk is negligible
- reports generated by automated scanning tools without accompanying analysis or proof of exploitability
- issues relating to versions that are no longer supported (Section 2.1).
