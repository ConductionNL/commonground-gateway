import React from 'react'
import { StyledContainer, StyledSubParagraph, StyledTextContainer, StyledHeading, StyledParagraph, StyledIntro, StyledImage } from './index.styles.js'
import ResponsiveImage from '../ResponsiveImage'

const Payoff = () => (
  <StyledContainer>
    <StyledIntro>
      <StyledTextContainer>
        <StyledHeading>Commonground Gateway</StyledHeading>
        <StyledParagraph>Conductor integreert meerdere API’s tot één (AJAX-first) rest-API die meteen in een Angular, React of Vue frontend kan worden geïntegreerd. 
</StyledParagraph>

      </StyledTextContainer>
    </StyledIntro>
    {/*<StyledImage>*/}
      {/*<ResponsiveImage src={couple_on_bike_near_city} alt="Illustratie van een koppel op een scooter in stad" />*/}
    {/*</StyledImage>*/}
  </StyledContainer>
)

export default Payoff
