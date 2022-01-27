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
    <SEO title="Commonground Gateway"/>
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
      <Section id="over-demodam">
        <Flex>
          <Box>
            <h2>Inleiding</h2>
            <p>
              Een Micro Service architectuur (zo als commonground) bestaat uit een groot aantal losse API’s en
              registers. Deze één voor één integreren in een voorkant is een tijdrovende en bijna ondoenlijke klus.
              Daarvoor is de common ground gateway ontwikkeld, deze koppelt gemakkelijk meerdere api’s aan elkaar vast
              tot één restfull api die meteen implementeerbaar. Zo fungeerde de gateway als schakel tussen te frontend
              en backend, waarmee het minder complex en sneller wordt om met (Common Ground) componenten een applicatie
              te ontwikkelen.
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
            <h2>Standaard rijk uitgevoerd</h2>
            <p>De commonground gateway bind api’s niet alleen aan elkaar vast maar voorziet ook in specifieke
              oplossingen voor het ecosysteem, zo kan hij objecten uit meerdere register als één object aan de ui
              leveren en ook bij het opslaan hiervan de gegevens weer over de verschillende API’s verdelen. Daarnaast
              kan de Gateway objecten verrijken, cashen en verwerkingen in het verwerkingen register vastleggen.
              Uiteraard worden standaard common ground componenten zoals autorisaties, notificaties en zgw volledig
              ondersteund. Hierdoor hoeft dit soort logica niet meer zelf de worden ontwikkeld en blijft de frontend ook
              echt de frontend</p>
          </Box>
        </Flex>
      </Section>

      <Section>
        <Flex>
          <Box width={3 / 5}>
            <h2>Standaard veilig</h2>
            <p>De commonground gateway ondersteund zonder externe afhankelijkheden DigiD, eHerkening, OpenId Connect en
              ADFS. Alternatief kan er ook gebruik worden gemaakt van kubernetes oplossingen zo als keycloak of
              commerciële oplossingen zoals Auth0. De gateway is zowel gepetest als aan een TPM audit onderworpen en
              levert met haar standaard ondersteuning voor PKI certificaten (zowel browser als machine tot machine) een
              veilige, stabiel en betrouwbaar platform voor applicatie ontwikkeling.
            </p>
          </Box>
          <Box width={2 / 5}>
            <ResponsiveImage src={selectionImage} alt="Illustratie van documenten met checklist"/>
          </Box>
        </Flex>
      </Section>

      <Section>
        <Flex>
          <Box width={2 / 5}>
            <ResponsiveImage src={casesImage} alt="Illustratie van persoon en document"/>
          </Box>
          <Box width={3 / 5}>
            <h2>Standaard sneller</h2>
            <p>Niet alleen is de gateway technisch sneller door slim gebruik van cashing, asynchrone bevragingen en
              andere
              aspecten van het common ground ecosysteem zo als linked data. Het is ook specifiek ontworpen voor sneller
              ontwikkelen, het kan snel en simpel locaal worden gedraaid en is in staat volledige API’s (zoals ZGW te
              mocken) hiermee kan een developer meteen aan de slag. De standaard uitgebreide functionaliteit zorg er
              voor
              dat developers zich niet langer zorgen hoeven te maken over aspecten zoals verwerkingen of DigiD maar zich
              volledig kunnen richten op de frontend. </p>
          </Box>
        </Flex>
      </Section>

      <Section>
        <Flex>
          <Box width={3 / 5}>
            <h2>Standaard in Standaarden</h2>
            <p>De common ground gateway is specifiek ontworpen met het ondersteunen van common ground applicaties in
              gedachte. De straightforward en simpele restful api is gemakkelijk te integreren in React of Angular
              applicaties zoals NL Design System en werkt goed samen met verschillende Ajax libraries (zoals restufll
              react). Deze ondersteuning gaat verder dan standaard lees verkeer en ondersteund bijvoorbeeld ook het
              realtime bijwerken van gegevens in de UI aan de hand van polling of webhooks.
            </p>
          </Box>
          <Box width={2 / 5}>
            <ResponsiveImage src={selectionImage} alt="Illustratie van documenten met checklist"/>
          </Box>
        </Flex>
      </Section>

      <Section>
        <Flex>
          <Box width={5 / 5}>
            <h2>Al met al, de commonground gateway, uw nieuwe standaard</h2>
            <p>Naast dat het ontwikkelen met (Common Ground) componenten door de Gateway een stuk eenvoudiger, lost het
              ook aan de technische kant een aantal problemen op.
              Omdat het functioneert als een tussenschakel zorgt dat de snelheid van de applicatie wordt verbeterd,
              omdat het intensieve proces die de frontend normaal draagt, wordt verheven naar de Gateway.
            </p>
            <p>
              Daarnaast is er meer flexibiliteit met betrekking tot de business logic, omdat binnen de Gateway extra
              property’s opgeslagen kunnen worden.
              Tot slot worden applicaties veiliger vanwege het eigen autorisatie mechanisme in de Gateway, waardoor een
              vaste key van een component niet meer in de frontend wordt verwerkt.
            </p>
            <p>
              Met de Gateway worden de haken en ogen die momenteel nog zitten aan het werken volgens de Common Ground
              principes opgelost. Complexe en tijdrovende koppelingen worden eenvoudiger en de veiligheid, snelheid en
              flexibiliteit van applicaties worden verbeterd.
            </p>
            <p>
              De Gateway is een component die in het vijflagen-model van Common Ground zit. De gateway is ontwikkeld en heeft drie voordelen: veiligheid, snelheid, flexibiliteit.
            </p>
            <p>
              De Gateway zorgt voor snelheid, omdat deze functioneert hier als een tussenschakel.
              De frontend hoeft niet meer verschillende componenten te bevragen en doet dit nu via de Gateway. Hierdoor intensieve proces die de frontend normaal draagt verheven naar de Gateway.
            </p>
            <p>
              Ook wordt door het eigen autorisatie mechanisme van de Gateway veiligheid gegarandeerd.
              Sommige componenten maken gebruik van een vaste key, hierdoor ontstaat de mogelijkheid om in bezit te komen van deze key, waarmee API’s specifiek bevraagd kunnen worden, wat natuurlijk niet veilig is.
            </p>
            <p>
              De Gateway gebruikt een eigen autorisatie mechanisme, waardoor de key, die gebruikt worden bij een componenten niet in de frontend wordt verwerkt.
            </p>
            <p>
              Daarnaast zorgt de Gateway voor meer flexibiliteit met betrekking tot de business logic.
              Stel er wordt gebruik gemaakt van een contactpersoon component. Dit component is zo ontwikkeld dat het alleen de naam, leeftijd en telefoonnummer opslaat voor het object persoon.
              Maar in de specifieke webapplicatie waar dit contact component wordt gebruikt wordt om nog meer informatie gevraagd voor het object persoon, namelijk naam, leeftijd, telefoonnummer, email en adres.
            </p>
            <p>
              De extra property’s; e-mail en adres moeten nu dus ergens worden opgeslagen. Het contact component doet dit niet, omdat deze niet is ontwikkeld om deze informatie op te slaan op de backend.
              De Gateway is ontwikkeld dat het hier de mogelijkheid geeft om extra property’s op te slaan in een EAV-object. De extra property’s worden hierbij opgeslagen in de Gateway.
              In feite zorgt de Gateway ervoor dat, voor welk object dan ook, er een mogelijkheid is om extra property’s op te slaan, wat zorgt voor flexibiliteit, wanneer een component niet overeenkomt met de business logic.
            </p>
            {/*<ResponsiveImage src={selectionImage} alt="Illustratie van documenten met checklist"/>*/}
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
