import React from 'react'
import { StyledContainer, StyledSubParagraph, StyledTextContainer, StyledHeading, StyledParagraph, StyledIntro, StyledImage } from './index.styles.js'
import ResponsiveImage from '../ResponsiveImage'

const Payoff = () => (
  <StyledContainer>
    <StyledIntro>
      <StyledTextContainer>
        <StyledHeading>Commonground Gateway</StyledHeading>
        <StyledParagraph>De commonground gateway integreerd meerdere API’s tot één (ajax-first) rest-api die meteen in een Angular of React front end kan worden geïntegreerd.</StyledParagraph>

      </StyledTextContainer>
    </StyledIntro>
    {/*<StyledImage>*/}
      {/*<ResponsiveImage src={couple_on_bike_near_city} alt="Illustratie van een koppel op een scooter in stad" />*/}
    {/*</StyledImage>*/}
  </StyledContainer>
)

export default Payoff
