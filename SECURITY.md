Security considerations
---

##Vulnerability Scanning
The repository is regularly scanned by [Snyk](https://snyk.io). This scan produces a list of vulnerabilities in the repository it scans. Usually we will aim at fixing a vulnerability as soon as possible, however, there are some exceptions for false-positives in the results of the scan. Also GitHub vulnerability scanning is enabled which also gives a security report periodically. For now this document only reports snyk vulnerabilities.

### Code exceptions
There is a small number of vulnerabilities mentioned by Snyk that will not be fixed due to them being false-positives.

#### MD5 Hashes
#####[Snyk CWE-916](https://app.snyk.io/org/klantinteractie-servicesysteem/project/83032e8f-9c92-42f1-a159-b88a7cb8ff0c#issue-dab84a22-7df7-44d4-9301-abe13de76499)
In the Snyk results it is mentioned that in some places in this gateway a MD5 hash is used, and that a stronger hashing algorithm should be used for passwords.
However, as the MD5 hashes are not used as credentials, but as cache identifiers, the use of MD5 does not have security implications. Therefore the priority to replace these MD5 hashes with a different algorithm is considered low.

#### Cross-site Scripting 
#####[Snyk CWE-79](https://app.snyk.io/org/klantinteractie-servicesysteem/project/83032e8f-9c92-42f1-a159-b88a7cb8ff0c#issue-2ee236ac-1097-4f52-9700-1060c5387f47)
There are two locations identified by Snyk where it detects the possibility of cross-site scripting due to insufficient sanitising of the data passed from input to output. This issue has been investigated thoroughly, and we decided there is no possibility of input leaking into the output of the response, therefore we decided this was a false positive that will not be fixed.

### Helm exceptions
There are some vulnerabilities mentioned by Snyk in our helm files that are also mentioned by the OWASP docker top 10.
Because of a number of reasons not all of these vulnerabilities are solved, and documented here as to why we cannot or will not solve them.

#### Resource Limits
#####[SNYK-CC-K8S-5](https://app.snyk.io/org/klantinteractie-servicesysteem/project/5bfcf19d-6432-4f02-9795-ea22b2b9c5c2#issue-SNYK-CC-K8S-5_[DocId: 0].input.spec.template.spec.containers[kiss-frontend].resources.limits.cpu)
#####[SNYK-CC-K8S-4](https://app.snyk.io/org/klantinteractie-servicesysteem/project/5bfcf19d-6432-4f02-9795-ea22b2b9c5c2#issue-SNYK-CC-K8S-4_[DocId: 0].input.spec.template.spec.containers[kiss-frontend].resources.limits.memory)
Both the OWASP Docker top 10 and Snyk expect Helm files to mention resource limits on CPU and memory. However, while Snyk and OWASP recommend this, Helm itself strongly discourages the use of forced resource limits because you cannot predict the kind of cluster the component is going to run on, 
instead, we present in the values.yaml suggested values based on average Kubernetes clusters, but they can be more relaxed on heavy duty clusters, or more stringent on clusters with less power.


##OWASP Docker top 10

#### Read-only Filesystems
Although we managed to get the PHP containers running on read-only filesystems, the containers used as DMZ with NGINX cannot run on read-only filesystems like the PHP containers due to the limitations of NGINX.
