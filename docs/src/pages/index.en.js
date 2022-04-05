import React from 'react'

import SEO from '../components/SEO'
import Navigation from '../components/Navigation'
import Layout from '../components/Layout'
import Flex from '../components/Flex'
import Box from '../components/Box'
import ResponsiveImage from '../components/ResponsiveImage'
import Container from '../components/Container'
import Footer from '../components/Footer'
import Section from '../components/Section'
import Payoff from '../components/Payoff'
import Heading from '../components/Heading'
import Span from '../components/Span'
import Background from '../components/Background'
import Logos from '../components/Logos'
import Timeline from '../components/Timeline'

import casesImage from '../images/undraw_file_analysis_8k9b.svg'
import selectionImage from '../images/undraw_personal_settings_kihd.svg'
import authorizationImage from '../images/undraw_two_factor_authentication_namy.svg'

const IndexPage = ({location}) => (
  <Layout>
    <SEO />
    <Background backgroundColor="#4376FC">
      <Container>
        <Navigation as="nav" location={location}/>
        <Section>
          <Payoff/>
        </Section>
      </Container>
    </Background>
    <Background backgroundColor="#202F3E">
      <Container>
        <Logos/>
      </Container>
    </Background>

    <Container>
      <Section id="common-ground-gateway">
        <Flex>
          <Box>
            <h2>The Common Gateway</h2>
            <p>A microservice architecture (such as Common Ground) consists of numerous APIs and /n registers. Integrating these one by one into a frontend is a time-consuming and almost impossible task. Due to this fact we developed the Common Gateway, it easily links multiple APIs into one RESTful API which can be implemented immediately. Because the Common Gateway functions as a link between the frontend and backend, it makes it less complex and faster to develop an application on the basis of (Common Ground) components.
            </p>
          </Box>
        </Flex>
      </Section>

      {/*<Section textAlign="center">*/}
      {/*  <iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/jTK-sbee2qM" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>*/}
      {/*</Section>*/}

      <Section>
        <Flex>
          <Box width={2 / 5}>
            <ResponsiveImage src={casesImage} alt="Illustratie van persoon en document"/>
          </Box>
          <Box width={3 / 5}>
            <h2>Richly implemented by default</h2>
            <p>The Common Gateway not only links APIs together, it also provides specific solutions for the ecosystem. It can deliver objects from multiple APIs as a single object to the UI and, when storing the data, also redistribute the data across the different APIs. In addition, the Common Gateway can enrich objects, cache them and record processing in a processing registry. Of course, standard Common Ground components such as authorizations, notifications and ZGW are fully supported. This means that it is no longer necessary to develop this type of logic yourself, and that the frontend really remains the frontend.
             </p>
          </Box>
        </Flex>
      </Section>

      <Section>
        <Flex>
          <Box width={3 / 5}>
            <h2>Secure by default</h2>
            <p>The Common Gateway supports DigiD, eHerkenning, OpenID Connect and ADFS without additional components. Alternatively, it is possible to use Kubernetes solutions, such as Keycloak or commercial solutions, such as Auth0. The Common Gateway has been both pen-tested and TPM-audited and delivers with its standard support for PKI certificates (both browser and machine to machine) a secure, stable and reliable platform for application development.
            </p>
          </Box>
          <Box width={2 / 5}>
            <ResponsiveImage src={selectionImage} alt="Illustratie van documenten met checklist"/>
          </Box>
        </Flex>
      </Section>

      <Section>
        <Flex>
          <Box width={3 / 5}>
            <h2>Faster by default</h2>
            <p>Not only is the Common Gateway technically faster through smart use of caching, asynchronous queries, and other aspects of the Common Ground ecosystem such as linked data. It is also specifically designed for faster development, it can be run locally, quickly and simply, and is capable of mocking full APIs (such as ZGW), allowing a developer to start right away. The standard extensive functionality ensures that developers no longer have to worry about aspects such as processing or DigiD, but can fully focus on the frontend.
            </p>
          </Box>
          <Box width={2 / 5}>
            <ResponsiveImage src={selectionImage} alt="Illustratie van documenten met checklist"/>
          </Box>
        </Flex>
      </Section>

      <Section>
        <Flex>
          <Box width={3 / 5}>
            <h2>Standard in Standards</h2>
            <p>The Common Gateway was designed specifically with supporting Common Ground applications in mind. The straightforward and simple RESTful API is easy to integrate into React or Angular applications, such as NL Design System, and works well with various AJAX libraries (such as RESTful React). This support goes beyond standard read traffic and also supports, for example, real-time updating of data in the UI using polling or webhooks
            </p>
          </Box>
          <Box width={2 / 5}>
            <ResponsiveImage src={selectionImage} alt="Illustratie van documenten met checklist"/>
          </Box>
        </Flex>
      </Section>

      <Section>
        <Flex>
          <Box width={3 / 5}>
            <h2>Both separate and in context</h2>
            <p>At the moment, the Common Gateway is only available as a separate container. However, during development, a conscious choice was made to reuse known PHP libraries. This makes it possible to run the Common Gateway as part (plug-in) of a WordPress, Drupal or Joomla installation. We aim to offer the Common Gateway as an integrable solution in 2022.
            </p>
          </Box>
          <Box width={2 / 5}>
            <ResponsiveImage src={selectionImage} alt="Illustratie van documenten met checklist"/>
          </Box>
        </Flex>
      </Section>


    </Container>

    {/*<Background backgroundColor="#202F3E">*/}
    {/*  <Container>*/}
    {/*    <Section>*/}
    {/*      <Heading align="center" fontSize="2rem">Roadmap</Heading>*/}
    {/*    </Section>*/}
    {/*  </Container>*/}
    {/*</Background>*/}

    {/*<Container>*/}
    {/*  <Timeline>*/}
    {/*    <Timeline.ContainerGreen align="left">*/}
    {/*      <Timeline.Content>*/}
    {/*        <Span fontSize="0.9rem">Juni 2021</Span>*/}
    {/*        <Heading as="h3" fontSize="1.5rem">Kick-off</Heading>*/}
    {/*        <p>Lancering van PDC</p>*/}
    {/*      </Timeline.Content>*/}
    {/*    </Timeline.ContainerGreen>*/}
    {/*    <Timeline.ContainerGreen align="right">*/}
    {/*      <Timeline.Content>*/}
    {/*        <Span fontSize="0.9rem">Juli 2021</Span>*/}
    {/*        <Heading as="h3" fontSize="1.5rem">Resultaat</Heading>*/}
    {/*        <p>Component installeerbaar</p>*/}
    {/*      </Timeline.Content>*/}
    {/*    </Timeline.ContainerGreen>*/}
    {/*  </Timeline>*/}
    {/*</Container>*/}
    <Footer/>
  </Layout>
)

export default IndexPage
