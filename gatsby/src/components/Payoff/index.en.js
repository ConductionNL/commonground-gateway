import React from 'react'
import {
    StyledContainer,
    StyledSubParagraph,
    StyledTextContainer,
    StyledHeading,
    StyledParagraph,
    StyledIntro,
    StyledImage
} from './index.styles.js'
import ResponsiveImage from '../ResponsiveImage'

const Payoff = () => (
  <StyledContainer>
    <StyledIntro>
      <StyledTextContainer>
        <StyledHeading>Producten en diensten catalogus</StyledHeading>
        <StyledParagraph>"Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum."</StyledParagraph>

      </StyledTextContainer>
    </StyledIntro>
    {/*<StyledImage>*/}
    {/*<ResponsiveImage src={couple_on_bike_near_city} alt="Illustratie van een koppel op een scooter in stad" />*/}
    {/*</StyledImage>*/}
  </StyledContainer>
)

export default Payoff
