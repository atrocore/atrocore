---
title: Developer Guide
metadata:
    title: Developer Guide
---

## Introduction for Developers

Atrocore is a powerful, multi-layered platform thoughtfully designed by developers who prioritize clean architecture, scalability, and long-term maintainability. Drawing inspiration from modern frameworks such as **Symfony**, Atrocore is developed using native **PHP** while integrating carefully chosen components to efficiently address specific technical challenges. For example, **Doctrine DBAL** is an essential part of our stack, handling all database interactions in a robust and consistent manner.

The system primarily operates with **PostgreSQL**, **MySQL**, or **MariaDB** as the main database engines, ensuring broad compatibility and flexibility for various project needs.

To manage complex and resource-intensive operations, Atrocore includes a comprehensive **Job Manager** subsystem. This component allows fine-grained control over the number of worker processes, scaling according to the server's capabilities to maintain optimal performance and reliability. Complementing this, the **Scheduled Jobs** feature offers an intuitive and flexible mechanism to define and automate recurring tasks, streamlining maintenance and operational workflows.

On the frontend side, Atrocore currently combines the established **BackboneJS** framework with modern **Svelte** components. This hybrid approach reflects an ongoing migration effort aimed at fully embracing **Svelte’s** modern paradigms and improving user experience while maintaining stability through the transition.

Beyond these core capabilities, Atrocore offers a rich set of features such as dynamic actions, customizable workflows, and real-time UI modifications. These tools provide developers with the power and flexibility to extend and adapt the system to a wide range of business requirements, making it an exciting and versatile platform to work with.

Atrocore is designed and rigorously tested to run exclusively on Linux servers, with Ubuntu (latest LTS) as the recommended operating system. Although the system may operate on other Linux distributions, Ubuntu remains our preferred environment to ensure maximum compatibility, performance, and quality assurance.

For detailed guidance on how to configure the system, debug issues, utilize the API effectively, and develop custom modules to extend or fully customize system behavior, please refer to the relevant sections throughout this Developer Guide.

---

## Recommendations

To ensure the best stability, performance, and compatibility with Atrocore, we strongly recommend the following environment setup:

- **Operating System:** Use the latest available Ubuntu LTS version. This platform is our development, testing, and production standard, guaranteeing the highest level of support.
- **PHP Version:** Install the default PHP version bundled with the chosen Ubuntu LTS release. This ensures full compatibility with all system components and reduces the risk of version conflicts.
- **Database:** PostgreSQL (pgsql) is the preferred primary database engine for Atrocore. It provides robustness, reliability, and comprehensive feature support aligned with our system’s requirements.

Adhering to these recommendations will help you avoid environment-specific issues and provide the smoothest development, testing, and deployment experience.

---

*Continue exploring this Developer Guide for comprehensive instructions on installation, configuration, debugging, API usage, and module development to harness the full potential of Atrocore.*


