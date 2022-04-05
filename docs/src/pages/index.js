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
            <h2>De Common Gateway</h2>
            <p>Een micro-service architectuur (zoals Common Ground) bestaat uit een groot aantal losse API’s en registers. Deze één voor één integreren in een voorkant is een tijdrovende en bijna ondoenlijke klus.
               Exact daarvoor is de Common Gateway ontwikkeld, deze koppelt gemakkelijk meerdere API’s aan elkaar vast tot één RESTful API die meteen implementeerbaar is. Zo functioneert de Common Gateway als schakel tussen de frontend en backend,
                waarmee het minder complex en sneller wordt om aan de hand van (Common Ground) componenten een applicatie te ontwikkelen. 
            </p>
          </Box>
        </Flex>
      </Section>

      {/*<Section textAlign="center">*/}
      {/*  <iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/jTK-sbee2qM" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>*/}
      {/*</Section>*/}

      <Section>
        <Flex>
          {/* <Box width={2 / 5}>
            <ResponsiveImage src={casesImage} alt="Illustratie van persoon en document"/>
          </Box> */}
          <Box width={5 / 5}>
            <h2>Standaard rijk uitgevoerd</h2>
            <p>De Common Gateway bindt API’s niet alleen aan elkaar vast, maar voorziet ook in specifieke oplossingen voor het ecosysteem. Het kan objecten uit meerdere API’s 
              als één object aan de UI leveren en bij het opslaan hiervan ook de gegevens weer over de verschillende API’s verdelen. Daarnaast kan de Common Gateway objecten verrijken, cachen en verwerkingen in een verwerkingen register vastleggen. Uiteraard worden standaard Common Ground componenten, zoals autorisaties, 
              notificaties en ZGW volledig ondersteund. Hierdoor hoeft dit soort logica niet meer zelf te worden ontwikkeld en blijft de frontend ook echt de 
              frontend.
              </p>
          </Box>
        </Flex>
      </Section>

      <Section>
        <Flex>
          <Box width={5 / 5}>
            <h2>Standaard veilig</h2>
            <p>De Common Gateway ondersteunt zonder extra componenten DigiD, eHerkenning, OpenID Connect en ADFS. Als alternatief kan er ook gebruik worden gemaakt van
               Kubernetes oplossingen, zoals Keycloak of commerciële oplossingen, zoals Auth0. De Common Gateway is zowel gepentest als aan een TPM audit onderworpen en
                levert met haar standaard ondersteuning voor PKI-certificaten (zowel browser als machine tot machine) een veilig, stabiel en betrouwbaar platform 
                voor applicatieontwikkeling.
            </p>
          </Box>
          {/* <Box width={2 / 5}>
            <ResponsiveImage src={selectionImage} alt="Illustratie van documenten met checklist"/>
          </Box> */}
        </Flex>
      </Section>

      <Section>
        <Flex>
          {/* <Box width={2 / 5}>
            <ResponsiveImage src={casesImage} alt="Illustratie van pefrsoon en document"/>
          </Box> */}
          <Box width={5 / 5}>
            <h2>Standaard sneller</h2>
            <p>Niet alleen is de Common Gateway technisch sneller door slim gebruik van caching, asynchrone bevragingen en andere aspecten van het Common Ground ecosysteem,
               zoals linked data. Het is ook specifiek ontworpen voor sneller ontwikkelen, het kan snel en simpel lokaal worden gedraaid en is in staat volledige API’s
                (zoals ZGW) te mocken, hiermee kan een developer meteen aan de slag. De standaard uitgebreide functionaliteit zorgt ervoor dat developers zich niet
                 langer zorgen hoeven te maken over aspecten, zoals verwerkingen of DigiD, maar zich volledig kunnen richten op de frontend. </p>
          </Box>
        </Flex>
      </Section>

<Section>
  <Flex>
    <Box width={5 / 5}>
      <h2>Standaard in Standaarden</h2>
      <p>De Common Gateway is specifiek ontworpen met het ondersteunen van Common Ground applicaties in gedachten. De straightforward en simpele RESTful API is
         gemakkelijk te integreren in React of Angular applicaties, zoals NL Design System en werkt goed samen met verschillende AJAX libraries (zoals 
         RESTful React). Deze ondersteuning gaat verder dan standaard lees verkeer en ondersteunt bijvoorbeeld ook het realtime bijwerken van gegevens 
         in de UI aan de hand van polling of webhooks.
      </p>
    </Box>
    {/* <Box width={2 / 5}>
      <ResponsiveImage src={selectionImage} alt="Illustratie van documenten met checklist"/>
    </Box> */}
  </Flex>
</Section>

<Section>
  <Flex>
    <Box width={5 / 5}>
      <h2>Zowel los als in samenhang</h2>
      <p>Op dit moment is de Common Gateay alleen als losse container beschikbaar, bij de ontwikkeling is er echter bewust gekozen voor het hergebruik van bekende PHP 
        libraries. Hierdoor is het mogelijk om de Common Gateway ook als onderdeel (plug-in) van een WordPress, Drupal of Joomla installatie te draaien. We streven er 
        naar om de Common Gateway in 2022 als integreerbare oplossing aan te bieden.
      </p>
    </Box>
    {/* <Box width={2 / 5}>
      <ResponsiveImage src={selectionImage} alt="Illustratie van documenten met checklist"/>
    </Box> */}
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
