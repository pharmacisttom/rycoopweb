# Member Portal — Future Activation

Member source and tables remain in the project, but production access is
disabled by default with `FEATURE_MEMBER_PORTAL`, `FEATURE_MEMBER_LOGIN`,
`FEATURE_MEMBER_ESERVICE`, and `FEATURE_MEMBER_FINANCIAL_DATA`.

Before activation, obtain approval and complete PDPA review, data mapping,
authorization review, penetration testing, and UAT. Verify ownership checks
for every member API. Then enable only the approved backend flags, set
`VITE_FEATURE_MEMBER_PORTAL=true` at build time, rebuild, retest, and deploy.
